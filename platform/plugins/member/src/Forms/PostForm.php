<?php

namespace Kern\Member\Forms;

use Kern\Base\Forms\FieldOptions\TagFieldOption;
use Kern\Base\Forms\FieldOptions\TextareaFieldOption;
use Kern\Base\Forms\Fields\TagField;
use Kern\Blog\Forms\PostForm as BasePostForm;
use Kern\Blog\Models\Post;
use Kern\Blog\Models\Tag;
use Kern\Member\Forms\Fields\CustomEditorField;
use Kern\Member\Http\Requests\PostRequest;

class PostForm extends BasePostForm
{
    public function setup(): void
    {
        parent::setup();

        $this
            ->model(Post::class)
            ->setFormOption('template', 'plugins/member::forms.base')
            ->hasFiles()
            ->setValidatorClass(PostRequest::class)
            ->setBreakFieldPoint('categories[]')
            ->remove('status')
            ->remove('is_featured')
            ->remove('content')
            ->addAfter(
                'description',
                'content',
                CustomEditorField::class,
                TextareaFieldOption::make()->label(trans('core/base::forms.content'))->rows(4)
            )
            ->modify(
                'tag',
                TagField::class,
                TagFieldOption::make()
                    ->label(trans('plugins/blog::posts.form.tags'))
                    ->when($this->getModel()->getKey(), function (TagFieldOption $fieldOption) {
                        /**
                         * @var Post $post
                         */
                        $post = $this->getModel();

                        return $fieldOption
                            ->value(
                                $post->tags()
                                    ->select('name')
                                    ->get()
                                    ->map(fn (Tag $item) => $item->name)
                                    ->implode(',')
                            );
                    })
                    ->placeholder(trans('plugins/blog::base.write_some_tags'))
                    ->ajaxUrl(route('public.member.tags.all')),
                true
            );
    }
}
