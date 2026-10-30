<?php

namespace Kern\Member\Tables;

use Illuminate\Database\Eloquent\Builder;
use Kern\Member\Models\Member;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Actions\DeleteAction;
use Kern\Table\Actions\EditAction;
use Kern\Table\BulkActions\DeleteBulkAction;
use Kern\Table\BulkChanges\CreatedAtBulkChange;
use Kern\Table\BulkChanges\EmailBulkChange;
use Kern\Table\BulkChanges\NameBulkChange;
use Kern\Table\Columns\CreatedAtColumn;
use Kern\Table\Columns\EmailColumn;
use Kern\Table\Columns\IdColumn;
use Kern\Table\Columns\ImageColumn;
use Kern\Table\Columns\NameColumn;
use Kern\Table\Columns\YesNoColumn;
use Kern\Table\HeaderActions\CreateHeaderAction;

class MemberTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Member::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('member.create'))
            ->addColumns([
                IdColumn::make(),
                ImageColumn::make('avatar_thumb_url')
                    ->title(trans('plugins/member::member.avatar'))
                    ->fullMediaSize()
                    ->relative(),
                NameColumn::make()->route('member.edit')->orderable(false),
                EmailColumn::make()->linkable(),
                CreatedAtColumn::make(),
            ])
            ->addActions([
                EditAction::make()->route('member.edit'),
                DeleteAction::make()->route('member.destroy'),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('member.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make()
                    ->name('first_name')
                    ->title(trans('plugins/member::member.first_name')),
                NameBulkChange::make()
                    ->name('last_name')
                    ->title(trans('plugins/member::member.last_name')),
                EmailBulkChange::make(),
                CreatedAtBulkChange::make(),
            ])
            ->queryUsing(function ($query) {
                return $query
                    ->select([
                        'id',
                        'avatar_id',
                        'first_name',
                        'last_name',
                        'email',
                        'created_at',
                        'confirmed_at',
                    ])
                    ->with(['avatar']);
            })
            ->onAjax(function (TableAbstract $table) {
                return $table->toJson(
                    $this->table
                        ->eloquent($this->query())
                        ->filter(function (Builder $query) {
                            $keyword = $this->request->input('search.value');

                            if (! $keyword) {
                                return $query;
                            }

                            return $query->where(function (Builder $query) use ($keyword): void {
                                $likeKeyword = '%'.$keyword.'%';

                                $query
                                    ->where('id', $keyword)
                                    ->orWhere('first_name', 'LIKE', $likeKeyword)
                                    ->orWhere('last_name', 'LIKE', $likeKeyword)
                                    ->orWhereRaw('concat(first_name, " ", last_name) LIKE ?', $likeKeyword)
                                    ->orWhere('email', 'LIKE', $likeKeyword)
                                    ->orWhereDate('created_at', $keyword);
                            });
                        })
                );
            });

        if (setting('verify_account_email', false)) {
            $this->addColumn(
                YesNoColumn::make('confirmed_at')
                    ->title(trans('plugins/member::member.email_verified')),
            );
        }
    }
}
