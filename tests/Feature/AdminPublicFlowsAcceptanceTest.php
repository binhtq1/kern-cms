<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Kern\Base\Events\SendMailEvent;
use Kern\Base\Supports\EmailAbstract;
use Kern\Contact\Forms\Fronts\ContactForm;
use Kern\Contact\Models\Contact;
use Kern\Contact\Models\CustomField;
use Kern\Member\Models\Member;
use Kern\Member\Notifications\ConfirmEmailNotification;
use Kern\Member\Notifications\ResetPasswordNotification;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminPublicFlowsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        config(['packages.installer.installer.enabled' => false, 'queue.default' => 'sync', 'mail.default' => 'array']);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        setting()->set(['theme' => 'ripple', 'member_enabled_registration' => '1', 'member_enabled_login' => '1', 'verify_account_email' => '1', 'receiver_emails' => '[{"value":"inbox@example.test"}]'])->save();
        Theme::setThemeName('ripple');
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        require plugin_path('member/routes/member.php');
        Route::getRoutes()->refreshNameLookups();
        Http::preventStrayRequests();
    }

    private function registration(string $email = 'member@example.test'): array
    {
        return ['first_name' => 'Acceptance', 'last_name' => 'Member', 'email' => $email, 'password' => 'member-password123', 'password_confirmation' => 'member-password123'];
    }

    public function test_contact_submission_validates_required_fields_persists_labels_and_replies_to_sender(): void
    {
        Mail::fake();
        $field = CustomField::query()->create(['name' => 'Department', 'type' => 'dropdown', 'required' => true, 'status' => 'published', 'order' => 2]);
        $field->options()->create(['label' => 'Support team', 'value' => 'support', 'order' => 0]);
        $payload = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'subject' => 'Acceptance question', 'content' => 'Test contact message'];
        $this->postJson(route('public.send.contact'), $payload)->assertUnprocessable()->assertJsonValidationErrors("contact_custom_fields.$field->id");
        $this->assertDatabaseCount('contacts', 0);
        $response = $this->postJson(route('public.send.contact'), [...$payload, 'contact_custom_fields' => [$field->id => 'support']])->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $contact = Contact::query()->sole();
        $this->assertSame(['Department' => 'Support team'], $contact->custom_fields);
        Mail::assertSent(EmailAbstract::class, fn (EmailAbstract $mail): bool => $mail->hasTo('inbox@example.test'));
        $this->actingAs(UserFactory::new()->superUser()->create());
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
        $this->get(route('contacts.edit', $contact), $headers)->assertOk()->assertJsonPath('props.contact.custom_fields.Department', 'Support team');
        $this->postJson(route('contacts.reply', $contact), ['message' => '<p>Reply from admin</p>'])->assertOk()->assertJsonPath('error', false);
        Mail::assertSent(EmailAbstract::class, fn (EmailAbstract $mail): bool => $mail->hasTo('sender@example.test') && $mail->hasSubject('Re: Acceptance question'));
        $this->assertDatabaseHas('contact_replies', ['contact_id' => $contact->id, 'message' => '<p>Reply from admin</p>']);
        $this->assertSame('read', $contact->fresh()->status->getValue());
    }

    public function test_contact_rejects_unknown_custom_field_ids_and_unconfigured_choice_values_without_writes(): void
    {
        Mail::fake();
        $field = CustomField::query()->create(['name' => 'Department', 'type' => 'dropdown', 'required' => true, 'status' => 'published', 'order' => 0]);
        $field->options()->create(['label' => 'Support', 'value' => 'support', 'order' => 0]);
        $payload = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'content' => 'Test contact message'];
        $this->postJson(route('public.send.contact'), [...$payload, 'contact_custom_fields' => [$field->id => 'unconfigured']])
            ->assertUnprocessable()->assertJsonValidationErrors("contact_custom_fields.$field->id");
        $this->postJson(route('public.send.contact'), [...$payload, 'contact_custom_fields' => [$field->id => 'support', 999999 => 'Injected']])
            ->assertUnprocessable()->assertJsonValidationErrors('contact_custom_fields');
        $this->assertDatabaseCount('contacts', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_member_registration_verification_and_duplicate_email_preserve_authentication_boundaries(): void
    {
        Notification::fake();
        $this->postJson(route('public.member.register.post'), [...$this->registration(), 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('members', 0);
        Notification::assertNothingSent();
        $this->postJson(route('public.member.register.post'), $this->registration())->assertOk()->assertJsonPath('error', false);
        $member = Member::query()->sole();
        $this->assertNull($member->confirmed_at);
        $this->assertGuest('member');
        Notification::assertSentTo($member, ConfirmEmailNotification::class);
        $this->postJson(route('public.member.register.post'), $this->registration())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('public.member.login.post'), ['email' => $member->email, 'password' => 'member-password123'])->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->get(route('public.member.confirm', ['user' => $member->id]))->assertNotFound();
        $this->get(URL::signedRoute('public.member.confirm', ['user' => $member->id]))->assertRedirect();
        $this->assertNotNull($member->fresh()->confirmed_at);
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_disabled_registration_and_login_do_not_create_or_authenticate_members(): void
    {
        Notification::fake();
        setting()->set(['member_enabled_registration' => '0', 'member_enabled_login' => '0'])->save();
        $this->postJson(route('public.member.register.post'), $this->registration())->assertNotFound();
        $this->postJson(route('public.member.login.post'), ['email' => 'member@example.test', 'password' => 'member-password123'])->assertNotFound();
        $this->assertDatabaseCount('members', 0);
        $this->assertGuest('member');
        Notification::assertNothingSent();
    }

    public function test_member_password_reset_tokens_cannot_be_reused_and_admin_password_change_applies_to_public_login(): void
    {
        Notification::fake();
        setting()->set('verify_account_email', '0')->save();
        $this->postJson(route('public.member.register.post'), $this->registration())->assertOk();
        $member = Member::query()->sole();
        $this->assertAuthenticatedAs($member, 'member');
        Auth::guard('member')->logout();
        $this->postJson(route('public.member.password.email'), ['email' => $member->email])->assertOk();
        Notification::assertSentTo($member, ResetPasswordNotification::class);
        $token = Password::broker('members')->createToken($member);
        $reset = ['email' => $member->email, 'token' => $token, 'password' => 'reset-password123', 'password_confirmation' => 'reset-password123'];
        $this->postJson(route('public.member.password.update'), $reset)->assertOk();
        Auth::guard('member')->logout();
        $this->postJson(route('public.member.password.update'), $reset)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->postJson(route('member.update', $member), ['first_name' => $member->first_name, 'last_name' => $member->last_name, 'email' => $member->email, 'is_change_password' => '1', 'password' => 'admin-password123', 'password_confirmation' => 'admin-password123'])
            ->assertOk()->assertJsonPath('error', false);
        $this->postJson(route('public.member.login.post'), ['email' => $member->email, 'password' => 'reset-password123'])->assertUnprocessable();
        $this->postJson(route('public.member.login.post'), ['email' => $member->email, 'password' => 'admin-password123'])->assertNoContent();
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_contact_reply_mail_failure_does_not_create_history_or_mark_contact_read(): void
    {
        $contact = Contact::query()->create(['name' => 'Sender', 'email' => 'sender@example.test', 'subject' => 'Question', 'content' => 'Contact body', 'status' => 'unread']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['queue.default' => 'database']);
        $this->mock(Mailer::class, function ($mailer): void {
            $mailer->shouldReceive('to')->once()->with('sender@example.test')->andThrow(new \RuntimeException('Acceptance mail transport failure'));
        });
        $this->postJson(route('contacts.reply', $contact), ['message' => '<p>Attempted reply</p>'])
            ->assertOk()->assertJsonPath('error', true)->assertJsonPath('message', trans('plugins/contact::contact.reply_failed'))->assertDontSee('Acceptance mail transport failure');
        $this->assertSame('database', config('queue.default'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('contact_replies', 0);
        $this->assertSame('unread', $contact->fresh()->status->getValue());
    }

    public function test_inertia_contact_mail_failure_returns_an_error_bag_without_creating_history_or_reporting_success(): void
    {
        $contact = Contact::query()->create(['name' => 'Sender', 'email' => 'sender@example.test', 'subject' => 'Question', 'content' => 'Contact body', 'status' => 'unread']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['queue.default' => 'database']);
        $this->mock(Mailer::class, function ($mailer): void {
            $mailer->shouldReceive('to')->once()->with('sender@example.test')->andThrow(new \RuntimeException('Private mail transport failure'));
        });
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
        $editUrl = route('contacts.edit', $contact);

        $this->from($editUrl)->post(route('contacts.reply', $contact), ['message' => '<p>Keep this reply draft</p>'], $headers)->assertRedirect($editUrl);
        $this->get($editUrl, $headers)->assertOk()->assertJsonPath('component', 'Contacts/Form')
            ->assertJsonPath('props.errors._request', trans('plugins/contact::contact.reply_failed'))
            ->assertJsonPath('props.flash.error', trans('plugins/contact::contact.reply_failed'))
            ->assertJsonPath('props.flash.success', null)->assertDontSee('Private mail transport failure');
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('contact_replies', 0);
        $this->assertSame('unread', $contact->fresh()->status->getValue());
    }

    #[TestWith(['number', 'not-number', '12'])]
    #[TestWith(['date', '2026-02-30', '2026-10-16'])]
    #[TestWith(['datetime', 'not-a-date', '2026-10-16T13:45'])]
    #[TestWith(['time', '27:99', '13:45'])]
    #[TestWith(['checkbox', '0', '1'])]
    #[TestWith(['radio', 'wrong-choice', 'configured'])]
    public function test_public_custom_field_types_reject_invalid_values_and_accept_native_wire_values(string $type, string $invalid, string $valid): void
    {
        Mail::fake();
        $field = CustomField::query()->create(['name' => 'Typed field', 'type' => $type, 'required' => true, 'status' => 'published', 'order' => 0]);
        if ($type === 'radio') {
            $field->options()->create(['label' => 'Configured label', 'value' => $valid, 'order' => 0]);
        }
        $payload = ['name' => 'Public Sender', 'email' => 'sender@example.test', 'content' => 'Typed contact message'];
        $this->postJson(route('public.send.contact'), [...$payload, 'contact_custom_fields' => [$field->id => $invalid]])
            ->assertUnprocessable()->assertJsonValidationErrors("contact_custom_fields.$field->id");
        $this->assertDatabaseCount('contacts', 0);
        Mail::assertNothingOutgoing();
        $response = $this->postJson(route('public.send.contact'), [...$payload, 'contact_custom_fields' => [$field->id => $valid]])->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_optional_public_custom_fields_can_be_omitted_and_render_in_configured_order(): void
    {
        Mail::fake();
        CustomField::query()->create(['name' => 'Optional last', 'type' => 'text', 'required' => false, 'status' => 'published', 'order' => 20]);
        CustomField::query()->create(['name' => 'Optional first', 'type' => 'text', 'required' => false, 'status' => 'published', 'order' => 1]);
        $html = ContactForm::create()->renderForm();
        $this->assertStringContainsString('Optional first', $html);
        $this->assertLessThan(strpos($html, 'Optional last'), strpos($html, 'Optional first'));
        $response = $this->postJson(route('public.send.contact'), ['name' => 'Optional Sender', 'email' => 'sender@example.test', 'content' => 'No optional data'])->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_ordinary_mail_events_remain_queued_and_debug_test_email_reports_transport_failure(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        event(new SendMailEvent('Queued content', 'Queued title', 'queue@example.test', []));
        $this->assertDatabaseCount('jobs', 1);
        Mail::assertNothingOutgoing();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->mock(Mailer::class, function ($mailer): void {
            $mailer->shouldReceive('to')->once()->with('test@example.test')->andThrow(new \RuntimeException('Acceptance test mail failure'));
        });
        $this->postJson(route('settings.email.test.send'), ['email' => 'test@example.test'])->assertOk()->assertJsonPath('error', true);
        $this->assertSame('database', config('queue.default'));
        $this->assertDatabaseCount('jobs', 1);
    }
}
