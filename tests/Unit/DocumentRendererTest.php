<?php

use App\Support\DocumentRenderer;

test('renders a paragraph with inline marks', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello '],
                    ['type' => 'text', 'text' => 'world', 'marks' => [['type' => 'bold']]],
                ],
            ],
        ],
    ];

    expect(DocumentRenderer::render($doc))->toBe('<p>Hello <strong>world</strong></p>');
});

test('renders headings, lists, blockquote, hr and hard break', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                ['type' => 'text', 'text' => 'Terms'],
            ]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]],
                ]],
            ]],
            ['type' => 'blockquote', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Quote']]],
            ]],
            ['type' => 'horizontalRule'],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'a'],
                ['type' => 'hardBreak'],
                ['type' => 'text', 'text' => 'b'],
            ]],
        ],
    ];

    $html = DocumentRenderer::render($doc);

    expect($html)->toContain('<h2>Terms</h2>')
        ->and($html)->toContain('<ul><li><p>One</p></li></ul>')
        ->and($html)->toContain('<blockquote><p>Quote</p></blockquote>')
        ->and($html)->toContain('<hr>')
        ->and($html)->toContain('a<br>b');
});

test('renders a table', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'table', 'content' => [
                ['type' => 'tableRow', 'content' => [
                    ['type' => 'tableHeader', 'content' => [['type' => 'text', 'text' => 'Head']]],
                    ['type' => 'tableCell', 'content' => [['type' => 'text', 'text' => 'Cell']]],
                ]],
            ]],
        ],
    ];

    expect(DocumentRenderer::render($doc))
        ->toContain('<table><tbody><tr><th>Head</th><td>Cell</td></tr></tbody></table>');
});

test('renders placeholder tokens from a placeholder node', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Agreement for '],
                ['type' => 'placeholder', 'attrs' => ['token' => 'occupant_name']],
            ]],
        ],
    ];

    expect(DocumentRenderer::render($doc))
        ->toContain('Agreement for {{occupant_name}}');
});

test('renders image with layout and dimensions and escapes src', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'image', 'attrs' => [
                'src' => '/media/logo.png" onerror="x',
                'alt' => 'logo',
                'width' => 120,
            ]],
        ],
    ];

    $html = DocumentRenderer::render($doc);

    expect($html)->toContain('<img')
        ->and($html)->toContain('data-layout="block"')
        ->and($html)->toContain('width:120px')
        ->and($html)->not->toContain('src="/media/logo.png" onerror')
        ->and($html)->toContain('logo.png&quot; onerror=&quot;x');
});

test('renders textStyle marks as inline styles', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Styled', 'marks' => [
                    ['type' => 'textStyle', 'attrs' => ['color' => '#A85A36', 'fontSize' => '18px']],
                ]],
            ]],
        ],
    ];

    $html = DocumentRenderer::render($doc);

    expect($html)->toContain('color:#A85A36')
        ->and($html)->toContain('font-size:18px');
});

test('renderJson decodes a JSON string', function () {
    $json = json_encode([
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]],
        ],
    ]);

    expect(DocumentRenderer::renderJson($json))->toBe('<p>Hi</p>');
});
