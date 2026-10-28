<?php

namespace Kern\SeoHelper\Entities\OpenGraph;

use Kern\SeoHelper\Bases\MetaCollection as BaseMetaCollection;

class MetaCollection extends BaseMetaCollection
{
    protected $prefix = 'og:';

    protected $nameProperty = 'property';
}
