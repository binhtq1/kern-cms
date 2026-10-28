<?php

namespace Kern\Theme\ThemeOption\Fields;

use Kern\Base\Traits\FieldOptions\HasAspectRatio;
use Kern\Base\Traits\FieldOptions\HasNumberItemsPerRow;
use Kern\Theme\Concerns\ThemeOption\Fields\HasOptions;
use Kern\Theme\ThemeOption\ThemeOptionField;

class UiSelectorField extends ThemeOptionField
{
    use HasAspectRatio;
    use HasNumberItemsPerRow;
    use HasOptions;

    public const RATIO_16_9 = '16:9';

    public const RATIO_9_16 = '9:16';

    public const RATIO_4_3 = '4:3';

    public const RATIO_3_4 = '3:4';

    public const RATIO_16_10 = '16:10';

    public const RATIO_10_16 = '10:16';

    public const RATIO_SQUARE = '1:1';

    public function fieldType(): string
    {
        return 'uiSelector';
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attributes' => [
                ...parent::toArray()['attributes'],
                'value' => $this->getValue(),
                'choices' => $this->options,
                'options' => [
                    'ratio' => $this->ratio,
                    'number_items_per_row' => $this->numberItemsPerRow,
                    'without_aspect_ratio' => $this->withoutAspectRatio,
                ],
            ],
        ];
    }
}
