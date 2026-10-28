<?php

namespace Kern\Member\Tables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Kern\Base\Models\BaseQueryBuilder;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Member\Models\Member;
use Kern\Member\Tables\Traits\ForMember;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\BulkChanges\SelectBulkChange;
use Kern\Table\BulkChanges\StatusBulkChange;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\FormattedColumn;
use Kern\Table\Columns\ImageColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\Columns\StatusColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class PostTable extends TableAbstract
{
    use ForMember;

    public function setup(): void
    {
        $this
            ->model(Post::class)
            ->addHeaderAction(CreateHeaderAction::make()->url(route('public.member.posts.create')))
            ->addColumns([
                CreatedAtColumn::make(),
                ImageColumn::make(),
                NameColumn::make()->route('public.member.posts.edit'),
                FormattedColumn::make('categories_name')
                    ->title(trans('plugins/blog::posts.categories'))
                    ->width(150)
                    ->orderable(false)
                    ->searchable(false)
                    ->getValueUsing(function (FormattedColumn $column) {
                        return implode(', ', $column->getItem()->categories->pluck('name')->all());
                    }),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addActions([
                EditAction::make()->route('public.member.posts.edit'),
                DeleteAction::make()->route('public.member.posts.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()
                    ->beforeDispatch(function (Post $model, array $ids): void {
                        foreach ($ids as $id) {
                            $post = Post::query()->findOrFail($id);

                            abort_if(auth('member')->id() !== $post->author_id, 403);
                        }
                    }),
            ])
            ->addFilters([
                NameBulkChange::make(),
                StatusBulkChange::make(),
                CreatedAtBulkChange::make(),
                SelectBulkChange::make()
                    ->name('category')
                    ->title(trans('plugins/blog::posts.category'))
                    ->searchable()
                    ->choices(fn () => Category::query()->pluck('name', 'id')->all()),
            ])
            ->queryUsing(function (EloquentBuilder $query) {
                return $query
                    ->with(['categories'])
                    ->select([
                        'id',
                        'name',
                        'image',
                        'created_at',
                        'status',
                        'updated_at',
                    ])
                    ->where([
                        'author_id' => auth('member')->id(),
                        'author_type' => Member::class,
                    ]);
            })
            ->onFilterQuery(
                function (
                    EloquentBuilder|QueryBuilder|EloquentRelation $query,
                    string $key,
                    string $operator,
                    ?string $value
                ) {
                    if (! $value || $key !== 'category') {
                        return false;
                    }

                    return $query->whereHas(
                        'categories',
                        fn (BaseQueryBuilder $query) => $query->where('categories.id', $value)
                    );
                }
            );
    }
}
