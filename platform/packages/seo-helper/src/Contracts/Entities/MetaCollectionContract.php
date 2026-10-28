<?php

namespace Kern\SeoHelper\Contracts\Entities;

use Kern\SeoHelper\Contracts\RenderableContract;

interface MetaCollectionContract extends RenderableContract
{
    /**
     * Add a meta to collection.
     *
     * @param  array  $item
     * @return $this
     */
    public function add($item);

    /**
     * Add many meta tags.
     *
     * @return $this
     */
    public function addMany(array $meta);

    /**
     * Remove a meta from the meta collection by key.
     *
     * @param  array|string  $names
     * @return $this
     */
    public function remove($names);
}
