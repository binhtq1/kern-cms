<?php

namespace Kern\SeoHelper\Contracts\Entities;

use Kern\SeoHelper\Contracts\RenderableContract;

interface WebmastersContract extends RenderableContract
{
    /**
     * Make Webmaster instance.
     *
     * @return $this
     */
    public static function make(array $webmasters = []);

    /**
     * Add a webmaster to collection.
     *
     * @param  string  $webmaster
     * @param  string  $content
     * @return $this
     */
    public function add($webmaster, $content);

    /**
     * Reset the webmaster collection.
     *
     * @return $this
     */
    public function reset();
}
