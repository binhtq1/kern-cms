<?php

namespace Kern\Setting\Supports;

use Inertia\Inertia;
use Inertia\Response;
use Kern\Analytics\Forms\AnalyticsSettingForm;
use Kern\Api\Forms\Settings\ApiSettingForm;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Supports\AdminAppearance;
use Kern\Blog\Forms\Settings\BlogSettingForm;
use Kern\Captcha\Forms\CaptchaSettingForm;
use Kern\Contact\Forms\Settings\ContactSettingForm;
use Kern\Language\Facades\Language;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Member\Forms\Settings\MemberSettingForm;
use Kern\Optimize\Forms\Settings\OptimizeSettingForm;
use Kern\Setting\Forms\AdminAppearanceSettingForm;
use Kern\Setting\Forms\CacheSettingForm;
use Kern\Setting\Forms\DataTableSettingForm;
use Kern\Setting\Forms\EmailRulesSettingForm;
use Kern\Setting\Forms\EmailSettingForm;
use Kern\Setting\Forms\EmailTemplateSettingForm;
use Kern\Setting\Forms\GeneralSettingForm;
use Kern\Setting\Forms\MediaSettingForm;
use Kern\Setting\Forms\SettingForm;
use Kern\Slug\Forms\SlugSettingForm;
use Kern\SocialLogin\Forms\SocialLoginSettingForm;
use Kern\Theme\Forms\Settings\WebsiteTrackingSettingForm;

class AdminSettingData
{
    public function fallbackReason(): ?string
    {
        if (request()->routeIs('settings.email', 'settings.email.update') && ! config('core.base.general.enable_email_configuration_from_admin_panel', true)) {
            return 'Email configuration is disabled in the admin panel.';
        }
        if (request()->routeIs('settings.email.template.edit', 'settings.email.template.update', 'settings.email.template.restore')) {
            $content = apply_filters('setting_email_template_meta_boxes', null, request()->route()->parameters());
            if (filled($content)) {
                return 'Blade is required by email template rendering extensions.';
            }
        }
        $route = preg_replace('/\.update$/', '', (string) request()->route()?->getName());
        $forms = [
            'settings.general' => GeneralSettingForm::class,
            'blog.settings' => BlogSettingForm::class,
            'contact.settings' => ContactSettingForm::class,
            'member.settings' => MemberSettingForm::class,
            'slug.settings' => SlugSettingForm::class,
            'settings.admin-appearance' => AdminAppearanceSettingForm::class,
            'settings.cache' => CacheSettingForm::class,
            'settings.datatables' => DataTableSettingForm::class,
            'settings.media' => MediaSettingForm::class,
            'settings.email' => EmailSettingForm::class,
            'settings.website-tracking' => WebsiteTrackingSettingForm::class,
            'api.settings' => ApiSettingForm::class,
            'optimize.settings' => OptimizeSettingForm::class,
            'settings.email.rules' => EmailRulesSettingForm::class,
            'settings.email.template' => EmailTemplateSettingForm::class,
            'settings.email.template.update-settings' => EmailTemplateSettingForm::class,
            'captcha.settings' => CaptchaSettingForm::class,
            'social-login.settings' => SocialLoginSettingForm::class,
            'analytics.settings' => AnalyticsSettingForm::class,
        ];
        if (isset($forms[$route]) && $this->fields($forms[$route]::create()) === null) {
            return 'Blade is required by unsupported settings fields.';
        }
        if ($route === 'settings.general' && apply_filters(BASE_FILTER_AFTER_SETTING_CONTENT, null)) {
            return 'Blade is required by additional settings content.';
        }

        return null;
    }

    public function render(SettingForm $form, ?string $submit = null): Response
    {
        $fields = $this->fields($form);
        abort_if($fields === null, 503, 'The settings integration does not provide supported React fields.');

        $fields = array_map(function (array $field): array {
            if ($field['type'] === 'checkbox') {
                $field['value'] = in_array($field['value'] ?? null, [true, 1, '1'], true) ? 1 : 0;
            }

            return $field;
        }, $fields);

        return Inertia::render('Settings/Form', [
            'kind' => $form instanceof MediaSettingForm ? 'media' : ($form instanceof EmailSettingForm ? 'email' : null),
            'emailNavigation' => request()->routeIs('settings.email*') ? $this->emailNavigation() : [],
            'languageNavigation' => $form instanceof SlugSettingForm && is_plugin_active('language-advanced') && is_plugin_active('language') ? $this->permalinkLanguageNavigation() : [],
            'title' => $form->getFormOption('section_title', __('Settings')),
            'description' => strip_tags($form->getFormOption('section_description', '')),
            'fields' => $fields,
            'submit' => $submit ?? $form->getFormOption('url') ?: request()->url(),
            'media' => request()->user()->hasPermission('media.index') ? ['list' => route('media.picker'), 'upload' => route('media.picker-upload')] : null,
            'extras' => [
                'testMail' => request()->routeIs('settings.email') ? route('settings.email.test.send') : null,
                'thumbnails' => request()->routeIs('settings.media') ? route('settings.media.generate-thumbnails') : null,
                'fileCount' => request()->routeIs('settings.media') ? MediaFile::query()->count() : 0,
            ],
        ]);
    }

    /** @return array<int, array{label: string, active: bool, url: string}> */
    protected function permalinkLanguageNavigation(): array
    {
        $languages = Language::getActiveLanguage(['lang_id', 'lang_name', 'lang_code', 'lang_flag']);
        $currentCode = Language::getCurrentAdminLocaleCode();
        $defaultCode = Language::getDefaultLocaleCode();

        return $languages->map(fn ($language): array => [
            'label' => $language->lang_name,
            'active' => $language->lang_code === $currentCode,
            'url' => route('slug.settings', $language->lang_code === $defaultCode ? [] : [Language::refLangKey() => $language->lang_code]),
        ])->values()->all();
    }

    /** @return array<int, array{label: string, active: bool, url: string}> */
    public function emailTemplateLanguageNavigation(array $parameters): array
    {
        if (! is_plugin_active('language')) {
            return [];
        }
        $languages = Language::getActiveLanguage(['lang_id', 'lang_name', 'lang_code', 'lang_flag']);
        if ($languages->count() < 2) {
            return [];
        }
        $currentCode = Language::getCurrentAdminLocaleCode();
        $defaultCode = Language::getDefaultLocaleCode();

        return $languages->map(fn ($language): array => [
            'label' => $language->lang_name,
            'active' => $language->lang_code === $currentCode,
            'url' => route('settings.email.template.edit', [...$parameters, ...($language->lang_code === $defaultCode ? [] : [Language::refLangKey() => $language->lang_code])]),
        ])->values()->all();
    }

    /** @return array<int, array{label: string, url: string}> */
    public function emailNavigation(): array
    {
        return [
            ['label' => 'Email delivery', 'url' => route('settings.email')],
            ['label' => 'Email templates', 'url' => route('settings.email.template')],
            ['label' => 'Email rules', 'url' => route('settings.email.rules')],
        ];
    }

    /** @return array<int, array<string, mixed>>|null */
    public function fields(FormAbstract $form): ?array
    {
        $form->prepareForSchema();
        if ($form->getMetaBoxes() || $form->hasSchemaRenderingOutput()) {
            return null;
        }
        $fields = [];
        $conditions = [];
        foreach ($form->getFields() as $name => $field) {
            $options = $field->getOptions();
            $type = class_basename($field);
            if ($type === 'HtmlField') {
                $content = (string) ($options['html'] ?? $options['content'] ?? '');
                if ($form instanceof GeneralSettingForm && $name === 'language_instruction' && is_plugin_active('language')) {
                    $fields[] = ['name' => $name, 'type' => 'notice', 'description' => trim(strip_tags($content)), 'description_html' => BaseHelper::clean($content)];

                    continue;
                }
                if ($form instanceof SlugSettingForm && $name === 'html') {
                    if (trim($content) === '') {
                        continue;
                    }
                }
                if (str_starts_with($name, 'open_fieldset_')) {
                    if (preg_match('/data-bb-trigger="\[name=([^\]]+)\]"/', $content, $trigger) && preg_match('/data-bb-value="([^"\n]*)"/', $content, $value)) {
                        $conditions[$name] = ['name' => $trigger[1], 'value' => html_entity_decode($value[1])];
                    } else {
                        $conditions[$name] = null;
                    }

                    continue;
                }
                if (str_starts_with($name, 'close_fieldset_')) {
                    unset($conditions['open_fieldset_'.substr($name, 15)]);

                    continue;
                }
                $special = $this->specialFields($name);
                if ($special !== null) {
                    foreach ($special as $item) {
                        $item['conditions'] = [...array_values(array_filter($conditions)), ...($item['conditions'] ?? [])];
                        $fields[] = $item;
                    }

                    continue;
                }
                if (preg_match('/^\s*<h4[^>]*>[^<]*<\/h4>\s*$|^\s*<\/?div[^>]*>\s*$/', $content)) {
                    continue;
                }

                return null;
            }
            if ($form instanceof SlugSettingForm && str_ends_with($name, '-model-key') && $type === 'InputType') {
                $fields[] = ['name' => $name, 'type' => 'hidden', 'value' => $options['value'] ?? '', 'label' => '', 'choices' => []];

                continue;
            }
            $types = [
                'TextField' => 'text', 'NumberField' => 'number', 'EmailField' => 'email',
                'PasswordField' => 'password', 'SelectField' => 'select', 'RadioField' => 'radio',
                'OnOffCheckboxField' => 'checkbox', 'OnOffField' => 'checkbox', 'CheckboxField' => 'checkbox',
                'ColorField' => 'color', 'GoogleFontsField' => 'font', 'CodeEditorField' => 'code',
                'TextareaField' => 'textarea', 'MediaImageField' => 'image', 'MediaImagesField' => 'images',
                'AlertField' => 'notice', 'UiSelectorField' => 'radio', 'TagField' => 'tags', 'RepeaterField' => 'repeater',
            ];
            if (! isset($types[$type])) {
                return null;
            }
            $name = preg_replace('/\[\]$/', '', $name);
            $value = $options['selected'] ?? $options['value'] ?? setting($name, '');
            if ($form instanceof AdminAppearanceSettingForm && array_key_exists($name, $defaults = app(AdminAppearance::class)->reactColorDefaults()) && blank(setting($name))) {
                $value = $defaults[$name];
            }
            $choices = ($options['choices'] ?? []) ?: ($options['values'] ?? []);
            $choices = collect($choices)->map(fn ($label, $key) => ['value' => (string) $key, 'label' => strip_tags(is_array($label) ? ($label['label'] ?? (string) $key) : (string) $label), ...($type === 'UiSelectorField' && is_array($label) && isset($label['image']) ? ['image' => (string) $label['image']] : [])])->values()->all();
            if ($types[$type] === 'select' && ($value === null || $value === '') && $choices && ! collect($choices)->contains('value', '')) {
                $value = $choices[0]['value'];
            }
            if ($types[$type] === 'font') {
                $choices = array_map(fn (string $font): array => ['value' => $font, 'label' => $font], json_decode(file_get_contents(core_path('base/resources/data/google-fonts.json')), true));
            }
            $secret = $type === 'PasswordField' || ($this->isSecret($name) && ! in_array($types[$type], ['checkbox', 'radio', 'select'], true));
            $fieldConditions = array_values(array_filter($conditions));
            if (preg_match('/^\[name=([^\]]+)\]$/', $options['wrapper']['data-bb-trigger'] ?? '', $trigger)) {
                $fieldConditions[] = ['name' => $trigger[1], 'value' => (string) ($options['wrapper']['data-bb-value'] ?? '')];
            }
            $description = strip_tags((string) ($options['help_block']['text'] ?? $options['html'] ?? $options['content'] ?? ''));
            if ($form instanceof AdminAppearanceSettingForm && $name === 'admin_primary_color') {
                $description = trans('core/base::admin.Custom colors apply to light mode. The primary color also applies in dark mode.');
            }
            $fields[] = [
                'name' => $name, 'type' => $secret ? 'password' : ($form instanceof ContactSettingForm && in_array($name, ['receiver_emails', 'blacklist_keywords'], true) ? 'tags' : $types[$type]),
                'label' => strip_tags((string) (($options['label'] ?? null) ?: str($name)->headline())),
                'description' => $description,
                'description_html' => BaseHelper::clean((string) ($options['help_block']['text'] ?? $options['html'] ?? $options['content'] ?? '')),
                'mode' => (string) ($options['attr']['mode'] ?? 'htmlmixed'),
                'readonly' => (bool) ($options['attr']['readonly'] ?? false),
                'value' => $secret ? '' : $value, 'secret' => $secret, 'stored' => $secret && filled($value),
                'disabled' => (bool) ($options['attr']['disabled'] ?? false),
                'placeholder' => (string) ($options['attr']['placeholder'] ?? ''),
                'choices' => $choices, 'conditions' => $fieldConditions,
                'children' => $options['fields'] ?? [],
            ];
        }

        if ($form instanceof SlugSettingForm && is_plugin_active('language') && is_plugin_active('language-advanced')) {
            $fields[] = ['name' => Language::refLangKey(), 'type' => 'hidden', 'value' => Language::getRefLang() ?? '', 'label' => '', 'choices' => []];
        }

        return $fields;
    }

    public function isSecret(string $name): bool
    {
        return (bool) preg_match('/(?:password|secret|private_key|credentials|postmark_token|client_secret|bunnycdn_key|backblaze_application_key)/i', $name);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function specialFields(string $name): ?array
    {
        $field = fn (string $key, string $type, mixed $value = null, array $extra = []) => [
            'name' => $key, 'type' => $type, 'label' => trans('core/setting::setting.'.($key === 'admin_email' ? 'general.admin_email' : 'media.'.str_replace('media_', '', $key))),
            'value' => $value ?? setting($key, ''), 'choices' => [], ...$extra,
        ];
        if (str_starts_with($name, 'media_size_') && str_ends_with($name, '_label')) {
            return [$field($name, 'notice', '', ['description' => str($name)->after('media_size_')->beforeLast('_label')->headline()->toString()])];
        }
        if ($name === 'optimize_fields') {
            $keys = ['page_speed_enable', 'collapse_white_space', 'elide_attributes', 'inline_css', 'insert_dns_prefetch', 'remove_comments', 'remove_quotes', 'defer_javascript'];

            return array_map(fn ($key) => $field('optimize_'.$key, 'checkbox', setting('optimize_'.$key, false), [
                'label' => trans('packages/optimize::optimize.'.($key === 'page_speed_enable' ? 'settings.enable' : $key)),
                'description' => $key === 'page_speed_enable' ? '' : trans('packages/optimize::optimize.'.$key.'_description'),
                'conditions' => $key === 'page_speed_enable' ? [] : [['name' => 'optimize_page_speed_enable', 'value' => '1']],
            ]), $keys);
        }
        if ($name === 'admin_email') {
            return [$field('admin_email', 'emails', get_admin_email()->all())];
        }
        if ($name === 'mailer') {
            $result = [];
            $drivers = [
                'smtp' => ['port', 'host', 'username', 'password', 'encryption', 'local_domain'],
                'mailgun' => ['mail_gun_domain', 'mail_gun_secret', 'mail_gun_endpoint'],
                'ses' => ['ses_key', 'ses_secret', 'ses_region'], 'postmark' => ['postmark_token'],
                'sendmail' => ['sendmail_path'], 'log' => ['log_channel'],
            ];
            foreach ($drivers as $driver => $names) {
                foreach ($names as $key) {
                    $key = 'email_'.$key;
                    $secret = $this->isSecret($key);
                    $result[] = $field($key, $secret ? 'password' : 'text', $secret ? '' : setting($key, $this->emailDefault($key)), [
                        'label' => trans('core/setting::setting.email.'.substr($key, 6)),
                        'secret' => $secret, 'stored' => $secret && filled(setting($key, $this->emailDefault($key))),
                        'conditions' => [['name' => 'email_driver', 'value' => $driver]],
                    ]);
                }
            }

            return $result;
        }
        if ($name === 'chunk_size_upload_file') {
            return [
                $field('media_chunk_enabled', 'checkbox', RvMedia::isChunkUploadEnabled(), ['label' => trans('core/setting::setting.media.enable_chunk'), 'description' => trans('core/setting::setting.enable_chunk_description')]),
                $field('media_chunk_size', 'number', setting('media_chunk_size', config('core.media.media.chunk.chunk_size')), ['conditions' => [['name' => 'media_chunk_enabled', 'value' => '1']]]),
                $field('media_max_file_size', 'number', setting('media_max_file_size', config('core.media.media.chunk.max_file_size')), ['conditions' => [['name' => 'media_chunk_enabled', 'value' => '1']]]),
            ];
        }
        if ($name === 'media_folders_can_add_watermark_field') {
            $selected = json_decode((string) setting('media_folders_can_add_watermark', '[]'), true) ?: [];

            return [
                $field('media_folders_can_add_watermark_all', 'checkbox', ! $selected, ['label' => __('Apply watermark to all folders')]),
                $field('media_folders_can_add_watermark', 'multiple', $selected, [
                    'label' => trans('core/setting::setting.media.media_folders_can_add_watermark'),
                    'description' => trans('core/setting::setting.media.all_helper_text'),
                    'conditions' => [['name' => 'media_folders_can_add_watermark_all', 'value' => '0']],
                    'choices' => MediaFolder::query()->where('parent_id', 0)->get(['id', 'name'])->map(fn ($folder) => ['value' => (string) $folder->id, 'label' => $folder->name])->all(),
                ]),
            ];
        }
        if ($name === 'upload_account_json_file') {
            return [$field('analytics_credentials_upload', 'json-upload', '', ['label' => __('Upload JSON credentials')])];
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function templates(): array
    {
        $templates = [];
        foreach (EmailHandler::getTemplates() as $type => $modules) {
            foreach ($modules as $module => $data) {
                foreach ($data['templates'] ?? [] as $key => $template) {
                    $params = [$type, $module, $key];
                    $settingKey = get_setting_email_status_key($type, $module, $key);
                    $templates[] = [
                        'title' => trans($template['title']), 'code' => $type.'.'.$module.'.'.$key,
                        'canOff' => (bool) ($template['can_off'] ?? false), 'group' => $type.'.'.$module, 'groupLabel' => trans($data['name'] ?? $module), 'description' => trans($template['description'] ?? ''),
                        'url' => route('settings.email.template.edit', $params),
                        'enabled' => (bool) setting($settingKey, $template['enabled'] ?? true),
                        'key' => $settingKey, 'statusUrl' => route('settings.email.template.status.update', $params),
                    ];
                }
            }
        }

        return $templates;
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(string $group = 'settings'): array
    {
        $groups = PanelSectionManager::getAllSections();

        return collect($groups[$group] ?? [])->map(fn ($section) => [
            'id' => $section->getId(),
            'title' => $section->getTitle(), 'description' => strip_tags($section->getDescription()),
            'items' => collect($section->getItems())->map(fn ($item) => [
                'title' => $item->getTitle(), 'description' => strip_tags($item->getDescription()), 'url' => $item->getUrl(),
            ])->values()->all(),
        ])->values()->all();
    }

    public function emailDefault(string $key): mixed
    {
        return match ($key) {
            'email_mail_gun_endpoint' => 'api.mailgun.net',
            'email_postmark_token' => config('services.postmark.token'),
            'email_ses_key' => config('services.ses.key'), 'email_ses_secret' => config('services.ses.secret'), 'email_ses_region' => config('services.ses.region'),
            'email_sendmail_path' => config('mail.mailers.sendmail.path'), 'email_log_channel' => config('mail.mailers.log.channel'),
            default => config('mail.mailers.smtp.'.substr($key, 6)),
        };
    }
}
