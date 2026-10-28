<?php

namespace Kern\SeoHelper\Contracts;

use Kern\SeoHelper\Contracts\Entities\DescriptionContract;
use Kern\SeoHelper\Contracts\Entities\MiscTagsContract;
use Kern\SeoHelper\Contracts\Entities\TitleContract;
use Kern\SeoHelper\Contracts\Entities\WebmastersContract;

interface SeoMetaContract extends RenderableContract
{
    /**
     * Set the Title instance.
     *
     * @return $this
     */
    public function title(TitleContract $title);

    /**
     * Set the Description instance.
     *
     * @return $this
     */
    public function description(DescriptionContract $description);

    /**
     * Set the MiscTags instance.
     *
     * @return $this
     */
    public function misc(MiscTagsContract $misc);

    /**
     * Set the Webmasters instance.
     *
     * @return $this
     */
    public function webmasters(WebmastersContract $webmasters);

    /**
     * Set the title.
     *
     * @param  string  $title
     * @param  string  $siteName
     * @param  string  $separator
     * @return $this
     */
    public function setTitle($title, $siteName = null, $separator = null);

    /**
     * Set the description content.
     *
     * @param  string  $content
     * @return $this
     */
    public function setDescription($content);

    /**
     * Add a webmaster tool site verifier.
     *
     * @param  string  $webmaster
     * @param  string  $content
     * @return $this
     */
    public function addWebmaster($webmaster, $content);

    /**
     * Set the current URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function setUrl($url);

    /**
     * Add a meta tag.
     *
     * @param  string  $name
     * @param  string  $content
     * @return $this
     */
    public function addMeta($name, $content);

    /**
     * Add many meta tags.
     *
     * @return $this
     */
    public function addMetas(array $meta);

    public function getTitle();

    public function getTitleOnly(): ?string;

    public function getDescription();
}
