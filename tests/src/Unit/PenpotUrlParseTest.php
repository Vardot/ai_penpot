<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_penpot\Unit;

use Drupal\ai_penpot\PenpotContextClient;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_penpot\PenpotContextClient
 *
 * @group ai_penpot
 */
class PenpotUrlParseTest extends UnitTestCase {

  /**
   * Data provider for ::testParsePenpotUrl().
   *
   * @return array<string, array{0:string, 1:string, 2:string}>
   *   Each case: url, expected file_id, expected page_id (both lowercased).
   */
  public static function urlProvider(): array {
    return [
      // A full workspace URL: /#/workspace/<teamId>/<fileId>?page-id=<pageId>.
      // The file id is the SECOND UUID in the path (the team id is skipped),
      // and the page id comes from the query string.
      'full workspace url' => [
        'https://design.penpot.app/#/workspace/11111111-1111-1111-1111-111111111111/22222222-2222-2222-2222-222222222222?page-id=33333333-3333-3333-3333-333333333333',
        '22222222-2222-2222-2222-222222222222',
        '33333333-3333-3333-3333-333333333333',
      ],
      // The explicit query form: /#/workspace?...&file-id=<f>&page-id=<p>.
      // The file-id / page-id query params win and are read directly.
      'workspace query form' => [
        'https://design.penpot.app/#/workspace?team-id=aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa&file-id=bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb&page-id=cccccccc-cccc-cccc-cccc-cccccccccccc',
        'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'cccccccc-cccc-cccc-cccc-cccccccccccc',
      ],
      // A read-only /#/view/<fileId> share link with a page id and index.
      'view link' => [
        'https://design.penpot.app/#/view/dddddddd-dddd-dddd-dddd-dddddddddddd?page-id=eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee&index=0',
        'dddddddd-dddd-dddd-dddd-dddddddddddd',
        'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
      ],
      // A bare file id (UUID) with no host: parsed as the file id, no page.
      'bare file id' => [
        '99999999-9999-9999-9999-999999999999',
        '99999999-9999-9999-9999-999999999999',
        '',
      ],
      // An "@"-prefixed link (AI prompts often paste links this way): the "@"
      // is stripped before parsing.
      'at-prefixed link' => [
        '@https://design.penpot.app/#/view/dddddddd-dddd-dddd-dddd-dddddddddddd?page-id=eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        'dddddddd-dddd-dddd-dddd-dddddddddddd',
        'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
      ],
      // Penpot ids are case-insensitive UUIDs: an uppercase id is normalised
      // to the lowercase form the RPC API expects.
      'uppercase uuid lowercased' => [
        'https://design.penpot.app/#/view/AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
        'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        '',
      ],
      // A non-Penpot string yields an empty result: no query params, no
      // /workspace|/view path segment, and not a bare UUID.
      'non-penpot string' => [
        'hello world this is not penpot',
        '',
        '',
      ],
    ];
  }

  /**
   * @covers ::parsePenpotUrl
   *
   * @dataProvider urlProvider
   */
  public function testParsePenpotUrl(string $url, string $expected_file_id, string $expected_page_id): void {
    $result = PenpotContextClient::parsePenpotUrl($url);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('file_id', $result);
    $this->assertArrayHasKey('page_id', $result);
    $this->assertSame($expected_file_id, $result['file_id']);
    $this->assertSame($expected_page_id, $result['page_id']);
  }

  /**
   * An empty string returns the empty result shape without error.
   *
   * @covers ::parsePenpotUrl
   */
  public function testParseEmptyString(): void {
    $this->assertSame(
      ['file_id' => '', 'page_id' => ''],
      PenpotContextClient::parsePenpotUrl(''),
    );
  }

}
