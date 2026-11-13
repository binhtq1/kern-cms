<?php

namespace Kern\Member\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Media\Models\MediaFile;
use Kern\Member\Forms\MemberForm;
use Kern\Member\Http\Requests\MemberCreateRequest;
use Kern\Member\Http\Requests\MemberEditRequest;
use Kern\Member\Models\Member;
use Kern\Member\Tables\MemberTable;

class MemberController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/member::member.menu_name'), route('member.index'));
    }

    public function index(MemberTable $dataTable)
    {
        return app(AdminContentResource::class)->index('members');
    }

    public function create()
    {
        return app(AdminContentResource::class)->form('members');
    }

    public function store(MemberCreateRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('members', $request)) {
            return $response;
        }

        $form = MemberForm::create();
        $form
            ->saving(function (MemberForm $form) use ($request): void {
                $member = $form->getModel();
                $member->fill($request->safe()->except(['password', 'status']));
                $member->status = $request->input('status', 'published');
                $member->confirmed_at = Carbon::now();
                $member->password = Hash::make($request->input('password'));

                if (
                    ($avatarInput = $request->input('avatar_image')) &&
                    $image = MediaFile::query()->where('url', $avatarInput)->first()
                ) {
                    $member->avatar_id = $image->getKey();
                }

                $member->save();
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('member.index')
            ->setNextRoute('member.edit', $form->getModel()->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Member $member)
    {
        return app(AdminContentResource::class)->form('members', $member);
    }

    public function update(Member $member, MemberEditRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('members', $request, $member)) {
            return $response;
        }

        MemberForm::createFromModel($member)
            ->saving(function (MemberForm $form) use ($request): void {
                $member = $form->getModel();
                $member->fill($request->safe()->except(['password', 'status']));
                if ($request->filled('status')) {
                    $member->status = $request->input('status');
                }

                if ($request->input('is_change_password') == 1) {
                    $member->password = Hash::make($request->input('password'));
                }

                if (
                    ($avatarInput = $request->input('avatar_image')) &&
                    $image = MediaFile::query()->where('url', $avatarInput)->first()
                ) {
                    $member->avatar_id = $image->getKey();
                }

                $member->save();
            });

        return $this
            ->httpResponse()
            ->setPreviousRoute('member.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Member $member): mixed
    {
        return app(AdminContentResource::class)->destroy('members', $member);
    }

    public function verifyEmail(int|string $id, Request $request)
    {
        $member = Member::query()
            ->where([
                'id' => $id,
                'confirmed_at' => null,
            ])->firstOrFail();

        $member->confirmed_at = Carbon::now();
        $member->save();

        event(new UpdatedContentEvent(MEMBER_MODULE_SCREEN_NAME, $request, $member));

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('member.index'))
            ->withUpdatedSuccessMessage();
    }
}
