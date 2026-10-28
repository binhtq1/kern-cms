<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Supports\Breadcrumb;
use Kern\Setting\Http\Requests\EmailTemplateRequest;
use Kern\Setting\Supports\AdminSettingData;
use Throwable;

class EmailTemplateController extends SettingController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()->add(
            trans('core/setting::setting.email.email_templates'),
            route('settings.email.template')
        );
    }

    public function index(string $type, string $module, string $template): View|Response
    {

        $title = trim(trans(config(sprintf('%s.%s.email.templates.%s.title', $type, $module, $template), '')));
        if ($title === '') {
            $title = in_array($template, ['header', 'footer'], true)
                ? trans('core/setting::setting.email.template_'.$template)
                : str($template)->headline()->toString();
        }
        $this->pageTitle($title);

        $emailContent = get_setting_email_template_content($type, $module, $template);
        $emailSubject = get_setting_email_subject($type, $module, $template);
        $pluginData = [
            'type' => $type,
            'name' => $module,
            'template_file' => $template,
        ];

        $routeParams = [$type, $module, $template, ...request()->only('ref_lang')];

        try {
            $updateUrl = route('settings.email.template.update', $routeParams);
            $restoreUrl = route('settings.email.template.restore', $routeParams);
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
            abort(404);
        }

        return Inertia::render('Settings/EmailTemplate', [
            'title' => $title,
            'content' => $emailContent, 'subject' => $emailSubject, 'hasSubject' => filled($emailSubject),
            'back' => route('settings.email.template'), 'icons' => EmailHandler::getIconVariables(),
            'emailNavigation' => app(AdminSettingData::class)->emailNavigation(),
            'languageNavigation' => app(AdminSettingData::class)->emailTemplateLanguageNavigation([$type, $module, $template]),
            'functions' => EmailHandler::getFunctions(),
            'subjectKey' => get_setting_email_subject_key($type, $module, $template),
            'module' => $module, 'template' => $template, 'submit' => $updateUrl, 'restore' => $restoreUrl,
            'preview' => route('settings.email.template.preview', $routeParams),
            'variables' => array_map(fn (string $label): string => strip_tags(trans($label)), EmailHandler::getVariables($type, $module, $template)),
        ]);
    }

    public function update(EmailTemplateRequest $request)
    {
        if ($request->has('email_subject_key')) {
            $this->saveSettings([
                $request->input('email_subject_key') => $request->input('email_subject'),
            ]);
        }

        $templatePath = get_setting_email_template_path($request->input('module'), $request->input('template_file'));

        BaseHelper::saveFileData($templatePath, $request->input('email_content'), false);

        return $this
            ->httpResponse()
            ->withUpdatedSuccessMessage();
    }
}
