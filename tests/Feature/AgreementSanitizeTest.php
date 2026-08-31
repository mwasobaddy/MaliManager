<?php

use App\Support\LeaseAgreementTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sanitize preserves Word like rich formatting', function () {
    $html = <<<'HTML'
        <h2 style="text-align: center;">Terms</h2>
        <p><span style="color: #A85A36; font-size: 18px; font-family: Georgia;">Styled</span>
        <strong>bold</strong> <em>italic</em> <u>under</u> <s>strike</s> x<sup>2</sup> H<sub>2</sub>O</p>
        <ul><li>One</li><li>Two</li></ul>
        <ol><li>First</li></ol>
        <blockquote>Quote</blockquote>
        <table><tbody><tr><td style="text-align: right;">Cell</td></tr></tbody></table>
        <p><a href="https://example.com" target="_blank">Link</a></p>
        <hr>
        <p>Placeholder {{occupant_name}} {{rent_amount}}</p>
        HTML;

    $clean = LeaseAgreementTemplate::sanitize($html);

    expect($clean)->toContain('<h2')
        ->and($clean)->toContain('text-align:center')
        ->and($clean)->toContain('color:#A85A36')
        ->and($clean)->toContain('font-size:18px')
        ->and($clean)->toContain('<strong>bold</strong>')
        ->and($clean)->toContain('<em>italic</em>')
        ->and($clean)->toContain('<u>under</u>')
        ->and($clean)->toContain('<s>strike</s>')
        ->and($clean)->toContain('<sup>2</sup>')
        ->and($clean)->toContain('<sub>2</sub>')
        ->and($clean)->toContain('<ul>')
        ->and($clean)->toContain('<ol>')
        ->and($clean)->toContain('<blockquote>')
        ->and($clean)->toContain('<table>')
        ->and($clean)->toContain('<td')
        ->and($clean)->toContain('href="https://example.com"')
        ->and($clean)->toContain('<hr')
        ->and($clean)->toContain('{{occupant_name}}')
        ->and($clean)->toContain('{{rent_amount}}');
});

test('sanitize strips scripts and event handlers', function () {
    $html = <<<'HTML'
        <p>Safe</p>
        <script>alert(1)</script>
        <img src="https://example.com/x.png" onerror="alert(2)">
        <iframe src="https://evil.example.com"></iframe>
        <a href="javascript:alert(3)">bad</a>
        <form><input name="x"></form>
        HTML;

    $clean = LeaseAgreementTemplate::sanitize($html);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('alert(1)')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->not->toContain('<form')
        ->and($clean)->not->toContain('<input');
});

test('sanitize keeps an image but strips handler attributes', function () {
    $html = '<p>Logo: <img src="/media/logo.png" width="120" onerror="alert(1)" style="width: 120px;"></p>';

    $clean = LeaseAgreementTemplate::sanitize($html);

    expect($clean)->toContain('<img')
        ->and($clean)->toContain('src="/media/logo.png"')
        ->and($clean)->not->toContain('onerror');
});
