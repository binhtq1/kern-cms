<?php

namespace Kern\LanguageAdvanced\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kern\ACL\Models\User;
use Kern\ACL\Services\ActivateUserService;
use Kern\Language\Facades\Language as LanguageFacade;
use Kern\Language\Models\Language;
use Kern\Language\Models\LanguageMeta;
use Kern\Page\Models\Page;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    public function test_translatable(): void
    {
        $languages = $this->createLanguages();

        $this->assertTrue(is_plugin_active('language') && is_plugin_active('language-advanced'));

        $user = $this->createUser();

        $this->actingAs($user);

        $this->assertAuthenticated();

        $englishTitle = 'This is a page in English';
        $vietnameseTitle = 'This is a page in Vietnamese';

        $testingLanguageCode = $languages[1]->lang_code;

        $page = Page::query()->create([
            'name' => $englishTitle,
            'user_id' => $user->getKey(),
        ]);

        $pageId = $page->getKey();

        $this
            ->get(route('pages.edit', $pageId))
            ->assertSee($englishTitle);

        DB::table('pages_translations')->insert([
            'lang_code' => $testingLanguageCode,
            'pages_id' => $pageId,
            'name' => $vietnameseTitle,
        ]);

        $this
            ->call('GET', route('pages.edit', $pageId), [LanguageFacade::refLangKey() => $testingLanguageCode])
            ->assertSee($vietnameseTitle);

        DB::table('pages_translations')->where([
            'lang_code' => $testingLanguageCode,
            'pages_id' => $pageId,
        ])->delete();

        $this
            ->call('GET', route('pages.edit', $pageId), [LanguageFacade::refLangKey() => $testingLanguageCode])
            ->assertSee($englishTitle);

        $page->delete();
    }

    protected function createUser(): User
    {
        Schema::disableForeignKeyConstraints();

        User::query()->truncate();

        $user = new User;
        $user->forceFill([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@domain.com',
            'username' => config('core.base.general.demo.account.username'),
            'password' => config('core.base.general.demo.account.password'),
            'super_user' => 1,
            'manage_supers' => 1,
        ]);
        $user->save();

        app(ActivateUserService::class)->activate($user);

        return $user;
    }

    protected function createLanguages(): array
    {
        $languages = [
            [
                'lang_name' => 'English',
                'lang_locale' => 'en',
                'lang_is_default' => true,
                'lang_code' => 'en_US',
                'lang_is_rtl' => false,
                'lang_flag' => 'us',
                'lang_order' => 0,
            ],
            [
                'lang_name' => 'Tiếng Việt',
                'lang_locale' => 'vi',
                'lang_is_default' => false,
                'lang_code' => 'vi',
                'lang_is_rtl' => false,
                'lang_flag' => 'vn',
                'lang_order' => 0,
            ],
        ];

        Language::query()->truncate();
        LanguageMeta::query()->truncate();

        $results = [];

        foreach ($languages as $item) {
            $results[] = Language::query()->create($item);
        }

        return $results;
    }
}
