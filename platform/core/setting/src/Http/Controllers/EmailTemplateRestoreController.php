<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Support\Facades\File;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Setting\Facades\Setting;
use Kern\Setting\Http\Requests\EmailTemplateRestoreRequest;

class EmailTemplateRestoreController extends BaseController
{
    public function __invoke(EmailTemplateRestoreRequest $request)
    {
        Setting::delete([$request->input('email_subject_key')]);

        $templatePath = get_setting_email_template_path($request->input('module'), $request->input('template_file'));

        if (File::exists($templatePath)) {
            File::delete($templatePath);
        }

        $shouldBeCleanedDirectories = [
            File::dirname($templatePath),
            storage_path('app/email-templates'),
        ];

        foreach ($shouldBeCleanedDirectories as $shouldBeCleanedDirectory) {
            if (File::isDirectory($shouldBeCleanedDirectory) && File::isEmptyDirectory($shouldBeCleanedDirectory)) {
                File::deleteDirectory($shouldBeCleanedDirectory);
            }
        }

        return $this
            ->httpResponse()
            ->setMessage(trans('core/setting::setting.email.reset_success'));
    }
}
