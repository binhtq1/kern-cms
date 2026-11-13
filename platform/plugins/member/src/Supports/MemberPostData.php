<?php

namespace Kern\Member\Supports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Rules\MediaImageRule;
use Kern\Base\Supports\AdminContentResource;
use Kern\Blog\Models\Post;
use Kern\Member\Forms\PostForm;
use Kern\Member\Http\Controllers\PostController;
use Kern\Member\Models\Member;

/** Reuse form serialization without inheriting administrator authorization. */
class MemberPostData extends AdminContentResource
{
    public function modules(): array
    {
        return ['posts' => ['class' => Post::class, 'form' => PostForm::class, 'route' => 'public.member.posts', 'screen' => 'MemberPosts', 'title' => trans('plugins/blog::posts.posts')]];
    }

    public function permissions(string $module): array
    {
        return ['create' => auth('member')->check(), 'edit' => auth('member')->check(), 'delete' => auth('member')->check()];
    }

    public function language(Model $model): ?string
    {
        return null;
    }

    protected function makeForm(string $module, Model $model): FormAbstract
    {
        $form = PostForm::createFromModel($model)->prepareForSchema();
        $form->remove(['author_id', 'author_type', 'status', 'is_featured']);

        return $form;
    }

    protected function fieldType(object $field): ?string
    {
        return class_basename($field) === 'CustomEditorField' ? 'editor' : parent::fieldType($field);
    }

    public function index(string $module): mixed
    {
        abort_unless($module === 'posts' && auth('member')->check(), 404);

        return app(PostController::class)->index(request());
    }

    protected function assertMemberPost(?Model $model): void
    {
        abort_unless(auth('member')->check(), 404);
        abort_unless($model === null || ($model instanceof Post && (! $model->exists || ((string) $model->author_id === (string) auth('member')->id() && $model->author_type === Member::class))), 404);
    }

    public function formData(string $module, ?Model $model = null): array
    {
        abort_unless($module === 'posts', 404);
        $this->assertMemberPost($model);
        $data = parent::formData($module, $model);
        $data['values'] = Arr::except($data['values'], ['status', 'is_featured', 'author_id', 'author_type']);
        $data['urls']['media'] = '';
        $data['urls']['mediaUpload'] = route('public.member.picker-upload');
        // Members may only use the shortcodes the existing member write API allows.
        $allowed = shortcode()->getCompiler()->whitelistShortcodes();
        $data['shortcodes'] = array_values(array_filter($data['shortcodes'], fn (array $item): bool => in_array($item['key'], $allowed, true)));

        return $data;
    }

    public function customFields(Model $model): array
    {
        if (! is_plugin_active('custom-field')) {
            return [];
        }

        add_custom_fields_rules_to_check([$model::class => $model->getKey(), 'model_name' => $model::class]);
        if ($model instanceof Post) {
            add_custom_fields_rules_to_check([
                $model::class.'_post_with_related_category' => $model->exists ? $model->categories()->allRelatedIds()->all() : [],
                $model::class.'_post_format' => $model->format_type,
            ]);
        }

        return get_custom_field_boxes($model::class, $model->getKey());
    }

    public function validateInput(Request $request, Post $post): void
    {
        $this->assertMemberPost($post);
        Validator::make($request->all(), [
            'slug' => ['nullable', 'string', 'max:250', 'regex:/^[\pL\pN_-]*$/u'],
            'seo_meta' => ['nullable', 'array:seo_title,seo_description,seo_image,index'],
            'seo_meta.seo_title' => ['nullable', 'string', 'max:250'],
            'seo_meta.seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_meta.seo_image' => ['nullable', 'string', new MediaImageRule],
            'seo_meta.index' => ['nullable', 'in:index,noindex'],
            'gallery' => ['nullable', 'json'],
            'custom_fields' => ['nullable', 'json'],
        ])->validate();
        if ($request->filled('gallery')) {
            Validator::make(['gallery' => json_decode($request->input('gallery'), true)], [
                'gallery' => ['array', 'max:100'],
                'gallery.*' => ['array:img,description'],
                'gallery.*.img' => ['required', 'string', new MediaImageRule],
                'gallery.*.description' => ['nullable', 'string', 'max:1000'],
            ])->validate();
        }
        if ($request->filled('custom_fields')) {
            $this->validateCustomFields($request, $post);
        }
    }
}
