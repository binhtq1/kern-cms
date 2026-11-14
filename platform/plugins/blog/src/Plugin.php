<?php

namespace Kern\Blog;

use Illuminate\Support\Facades\Schema;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Tag;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\Menu\Models\MenuNode;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;
use Kern\Setting\Facades\Setting;
use Kern\Widget\Models\Widget;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('post_tags');
        Schema::dropIfExists('post_categories');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('posts_translations');
        Schema::dropIfExists('categories_translations');
        Schema::dropIfExists('tags_translations');

        Widget::query()
            ->where('widget_id', 'widget_posts_recent')
            ->each(fn (DashboardWidget $dashboardWidget) => $dashboardWidget->delete());

        MenuNode::query()
            ->whereIn('reference_type', [Category::class, Tag::class])
            ->each(fn (MenuNode $menuNode) => $menuNode->delete());

        Setting::delete([
            'blog_post_schema_enabled',
            'blog_post_schema_type',
        ]);
    }
}
