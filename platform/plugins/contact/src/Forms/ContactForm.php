<?php

namespace Kern\Contact\Forms;

use Kern\Base\Forms\FieldOptions\StatusFieldOption;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\FormAbstract;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Http\Requests\EditContactRequest;
use Kern\Contact\Models\Contact;

class ContactForm extends FormAbstract
{
    public function setup(): void
    {

        $this
            ->model(Contact::class)
            ->setValidatorClass(EditContactRequest::class)
            ->add(
                'status',
                SelectField::class,
                StatusFieldOption::make()
                    ->choices(ContactStatusEnum::labels())
            )
            ->setBreakFieldPoint('status')
            ->addMetaBoxes([
                'information' => [
                    'title' => trans('plugins/contact::contact.contact_information'),
                    'content' => view('plugins/contact::contact-info', ['contact' => $this->getModel()])->render(),
                ],
                'replies' => [
                    'title' => trans('plugins/contact::contact.replies'),
                    'content' => view('plugins/contact::reply-box', ['contact' => $this->getModel()])->render(),
                ],
            ]);
    }
}
