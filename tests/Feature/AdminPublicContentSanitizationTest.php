<?php

namespace Tests\Feature;

use Kern\Base\Facades\BaseHelper;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminPublicContentSanitizationTest extends TestCase
{
    #[TestWith(['left'])]
    #[TestWith(['right'])]
    #[TestWith(['none'])]
    public function test_public_html_preserves_safe_image_layout_and_rejects_unsafe_styles(string $float): void
    {
        $html = BaseHelper::clean('<p><img src="/storage/photo.jpg" alt="Photo" style="width:320px;float:'.$float.';margin:0 16px 16px 0;position:fixed;background-image:url(javascript:alert(1))" onerror="alert(1)"></p><script>alert(1)</script><a href="javascript:alert(1)">Unsafe</a>');

        $this->assertStringContainsString('float:'.$float, $html);
        $this->assertStringContainsString('width:320px', $html);
        $this->assertStringContainsString('margin:0 16px 16px 0', $html);
        $this->assertStringNotContainsString('position:', $html);
        $this->assertStringNotContainsString('background-image', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_public_html_rejects_invalid_float_values(): void
    {
        $html = BaseHelper::clean('<p><img src="/storage/photo.jpg" alt="Photo" style="float:expression(alert(1));width:320px"></p>');

        $this->assertStringNotContainsString('float:', $html);
        $this->assertStringNotContainsString('expression', $html);
        $this->assertStringContainsString('width:320px', $html);
    }

    public function test_public_html_preserves_table_alignment_and_image_caption(): void
    {
        $html = BaseHelper::clean('<figure><img src="/storage/photo.jpg" alt="Photo"><figcaption style="text-align:center">Image caption</figcaption></figure><table style="width:100%;border-collapse:collapse"><tbody><tr><td style="text-align:right">Aligned cell</td></tr></tbody></table><p style="text-align:center">Centered paragraph</p>');

        $this->assertStringContainsString('<figcaption style="text-align:center;">Image caption</figcaption>', $html);
        $this->assertStringContainsString('<td style="text-align:right;">Aligned cell</td>', $html);
        $this->assertStringContainsString('border-collapse:collapse', $html);
        $this->assertStringContainsString('<p style="text-align:center;">Centered paragraph</p>', $html);
    }
}
