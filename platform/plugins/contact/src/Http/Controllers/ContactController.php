<?php

namespace Kern\Contact\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Forms\ContactForm;
use Kern\Contact\Http\Requests\ContactReplyRequest;
use Kern\Contact\Http\Requests\EditContactRequest;
use Kern\Contact\Models\Contact;
use Kern\Contact\Models\ContactReply;
use Kern\Contact\Tables\ContactTable;
use Throwable;

class ContactController extends BaseController
{
    public function export(Request $request, ContactTable $dataTable): mixed
    {
        $filters = $request->validate([
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(ContactStatusEnum::labels()))],
        ]);
        $filterRequest = clone $request;
        $dataTable->queryUsing(function ($query) use ($filters, $filterRequest): void {
            $query->select(['id', 'name', 'phone', 'email', 'created_at', 'status']);
            app(AdminRegisteredTableData::class)->applyFilters('contacts', $query, $filterRequest);
            if ($search = $filters['search'] ?? null) {
                $query->where(fn ($query) => $query->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%'));
            }
            if ($status = $filters['status'] ?? null) {
                $query->where('status', $status);
            }
        });
        $request->replace(['action' => $filters['format'] === 'xlsx' ? 'excel' : 'csv']);
        $request->query->replace([]);

        return $filters['format'] === 'xlsx' ? $dataTable->excel() : $dataTable->csv();
    }

    public function index(ContactTable $dataTable)
    {
        return app(AdminContentResource::class)->index('contacts');
    }

    public function edit(Contact $contact)
    {
        return app(AdminContentResource::class)->form('contacts', $contact);
    }

    public function update(Contact $contact, EditContactRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('contacts', $request, $contact)) {
            return $response;
        }

        ContactForm::createFromModel($contact)->setRequest($request)->onlyValidatedData()->save();

        return $this
            ->httpResponse()
            ->setPreviousRoute('contacts.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Contact $contact): mixed
    {
        return app(AdminContentResource::class)->destroy('contacts', $contact);
    }

    public function postReply(Contact $contact, ContactReplyRequest $request)
    {
        $message = BaseHelper::clean($request->input('message'));

        if (! $message) {
            throw ValidationException::withMessages(['message' => trans('validation.required', ['attribute' => 'message'])]);
        }

        try {
            EmailHandler::send($message, sprintf('Re: %s', $contact->subject), $contact->email, [], true);
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);

            $message = trans('plugins/contact::contact.reply_failed');
            if ($request->header('X-Inertia')) {
                return redirect()->route('contacts.edit', $contact)->with('error_msg', $message)->withErrors(['_request' => $message]);
            }

            return $this->httpResponse()->setError()->setMessage($message);
        }

        ContactReply::query()->create([
            'message' => $message,
            'contact_id' => $contact->getKey(),
        ]);

        $contact->status = ContactStatusEnum::READ();
        $contact->save();

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/contact::contact.message_sent_success'));
    }
}
