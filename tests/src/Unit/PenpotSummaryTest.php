<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_penpot\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_penpot\PenpotContextClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;

/**
 * Unit-tests the pure design-context helpers of the Penpot client.
 *
 * Both extractText() and summarizeTokens() reason over a hand-built Penpot API
 * response array - they never touch the HTTP client, config or the Key module -
 * so they can be exercised with no Drupal bootstrap. The constructor arguments
 * are mocked purely to instantiate the class for the one instance method.
 *
 * @coversDefaultClass \Drupal\ai_penpot\PenpotContextClient
 *
 * @group ai_penpot
 */
class PenpotSummaryTest extends UnitTestCase {

  /**
   * Builds a client with inert (mocked) dependencies.
   */
  protected function client(): PenpotContextClient {
    return new PenpotContextClient(
      $this->createMock(ClientInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      NULL,
    );
  }

  /**
   * A full paragraph tree joins runs per paragraph, one line per paragraph.
   *
   * @covers ::extractText
   */
  public function testExtractTextJoinsParagraphs(): void {
    $content = [
      'type' => 'root',
      'children' => [
        [
          'type' => 'paragraph-set',
          'children' => [
            [
              'type' => 'paragraph',
              'children' => [
                ['text' => 'Hello '],
                ['text' => 'world'],
              ],
            ],
            [
              'type' => 'paragraph',
              'children' => [
                ['text' => 'Second line'],
              ],
            ],
          ],
        ],
      ],
    ];

    $this->assertSame(
      "Hello world\nSecond line",
      PenpotContextClient::extractText($content),
    );
  }

  /**
   * With no paragraph nodes, the flat fallback still returns the run text.
   *
   * @covers ::extractText
   */
  public function testExtractTextFallsBackToFlatRuns(): void {
    $content = ['children' => [['text' => 'Just runs']]];
    $this->assertSame('Just runs', PenpotContextClient::extractText($content));
  }

  /**
   * An empty content tree yields an empty string, not an error.
   *
   * @covers ::extractText
   */
  public function testExtractTextEmpty(): void {
    $this->assertSame('', PenpotContextClient::extractText([]));
  }

  /**
   * The first text run's style is returned; NULL when there is no text run.
   *
   * @covers ::firstTextStyle
   */
  public function testFirstTextStyle(): void {
    $content = [
      'children' => [
        [
          'type' => 'paragraph',
          'children' => [
            ['text' => 'Hi', 'fontFamily' => 'Inter', 'fontWeight' => '700', 'fontSize' => '32'],
          ],
        ],
      ],
    ];
    $style = PenpotContextClient::firstTextStyle($content);
    $this->assertIsArray($style);
    $this->assertSame('Inter', $style['fontFamily']);
    $this->assertSame('32', $style['fontSize']);

    $this->assertNull(PenpotContextClient::firstTextStyle([]));
  }

  /**
   * Test summarizeTokens() distils colours, typography, text and the outline.
   *
   * @covers ::summarizeTokens
   */
  public function testSummarizeTokens(): void {
    $page = [
      'name' => 'Home',
      'objects' => [
        // The auto root frame is skipped from the outline.
        'root' => ['name' => 'Root Frame', 'type' => 'frame'],
        // A fully opaque solid fill: no percentage suffix on the colour label.
        'hero' => [
          'name' => 'Hero',
          'type' => 'rect',
          'fills' => [['fillColor' => '#FF0000', 'fillOpacity' => 1.0]],
        ],
        // A text shape: contributes a colour (with opacity suffix), a
        // typography entry and the real text content.
        'title' => [
          'name' => 'Title',
          'type' => 'text',
          'fills' => [['fillColor' => '#112233', 'fillOpacity' => 0.5]],
          'content' => [
            'type' => 'root',
            'children' => [
              [
                'type' => 'paragraph-set',
                'children' => [
                  [
                    'type' => 'paragraph',
                    'children' => [
                      ['text' => 'Welcome', 'fontFamily' => 'Inter', 'fontWeight' => '700', 'fontSize' => '32'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $summary = $this->client()->summarizeTokens($page);

    // Colours: keyed by label, valued by the owning shape name.
    $this->assertArrayHasKey('#FF0000', $summary['colors']);
    $this->assertSame('Hero', $summary['colors']['#FF0000']);
    $this->assertArrayHasKey('#112233 (50%)', $summary['colors']);
    $this->assertSame('Title', $summary['colors']['#112233 (50%)']);

    // Typography key is "<family> <weight> <size>" with a px suffix, valued by
    // the shape name.
    $this->assertArrayHasKey('Inter 700 32px', $summary['typography']);

    // The real text content is captured verbatim with its size.
    $this->assertSame('Welcome', $summary['texts'][0]['text']);
    $this->assertSame('Title', $summary['texts'][0]['name']);
    $this->assertSame(32.0, $summary['texts'][0]['size']);

    // The outline lists named shapes as "type: name" and skips the root frame.
    $this->assertContains('rect: Hero', $summary['outline']);
    $this->assertContains('text: Title', $summary['outline']);
    foreach ($summary['outline'] as $line) {
      $this->assertStringNotContainsString('Root Frame', $line);
    }

    // The root name carries through.
    $this->assertSame('Home', $summary['root_name']);
  }

}
