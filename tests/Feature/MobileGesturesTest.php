<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two mobile gesture decisions, pinned because both are the kind that a
 * later convenience quietly undoes.
 *
 * Double-tapping a control used to zoom the page instead of pressing it twice.
 * The cheap fix is user-scalable=no in the viewport, and it is the wrong one: it
 * takes pinch-zoom away from everyone who needs it. The fix taken instead is
 * touch-action on the controls, which drops the double-tap gesture and leaves
 * pinch alone.
 */
class MobileGesturesTest extends TestCase
{
    use RefreshDatabase;

    private function stylesheet(): string
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertIsString($css, 'The stylesheet is missing, so nothing was checked.');

        return $css;
    }

    public function test_the_page_never_forbids_pinch_zoom(): void
    {
        $html = (string) $this->get(route('diary.index'))->assertOk()->getContent();

        preg_match('/<meta name="viewport" content="([^"]*)"/', $html, $viewport);

        $this->assertNotEmpty($viewport, 'No viewport meta at all, so nothing was checked.');
        $this->assertStringContainsString('width=device-width', $viewport[1]);
        $this->assertStringNotContainsString('user-scalable', $viewport[1]);
        $this->assertStringNotContainsString('maximum-scale', $viewport[1]);
    }

    public function test_controls_opt_out_of_the_double_tap_gesture(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/^[^{}\n]*\bbutton\b[^{}\n]*\{[^{}]*touch-action:\s*manipulation/m',
            $css,
            'Buttons should opt out of double-tap zoom.',
        );

        // `none` would also stop the double tap, and would stop scrolling and
        // pinching with it.
        $this->assertStringNotContainsString('touch-action: none', $css);
    }

    /**
     * The buttons repeat while held, which is scripting; the number beside them
     * is a real input, which is not. Both halves are asserted together because
     * the enhancement is only allowed to exist while the baseline does.
     */
    public function test_the_stepper_is_an_enhancement_over_an_editable_number(): void
    {
        $html = (string) $this->get(route('goal.edit'))->assertOk()->getContent();

        preg_match_all('/<button type="button" class="(?:step|mg)-btn"([^>]*)>/', $html, $buttons);

        $this->assertNotEmpty($buttons[1], 'No stepper buttons on the goal screen.');

        foreach ($buttons[1] as $attributes) {
            $this->assertStringContainsString('data-step-target="', $attributes);
            $this->assertStringContainsString('data-step-delta="', $attributes);
        }

        // Every target a button steps must exist as an input that can be typed
        // into instead, and must carry the bounds the repeat clamps to.
        preg_match_all('/data-step-target="([^"]+)"/', $html, $targets);

        foreach (array_unique($targets[1]) as $id) {
            $this->assertMatchesRegularExpression(
                '/<input type="number" id="'.preg_quote($id, '/').'"[^>]*min="[^"]*"[^>]*max="[^"]*"/',
                $html,
                "The {$id} stepper has no bounded input behind it.",
            );
        }
    }
}
