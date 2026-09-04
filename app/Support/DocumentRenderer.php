<?php

namespace App\Support;

/**
 * Renders a Tiptap JSON document node tree back to HTML on the server.
 *
 * The agreement/receipt builder edits structured JSON (blocks) and the client
 * also sends the derived `body_html` from Tiptap's getHTML(). This class is the
 * server-side counterpart: it lets us render the same block JSON to HTML in
 * PHP (for server-generated receipts, PDF/DOCX export, and email) without
 * relying on the client, and it mirrors the node/mark set that the editor
 * produces.
 *
 * Supported nodes: paragraph, headings, lists, blockquote, codeBlock,
 * horizontalRule, hardBreak, table (row/header/cell), image, and a
 * placeholder-token (data) node. Supported marks: bold, italic, strike,
 * underline, code, link, subscript, superscript, and textStyle-driven color,
 * fontSize and fontFamily attributes (rendered as inline styles).
 */
class DocumentRenderer
{
    /**
     * @param  array<string, mixed>|null  $document  A parsed Tiptap JSON doc node.
     */
    public static function render(?array $document): string
    {
        if (! is_array($document)) {
            return '';
        }

        $content = $document['content'] ?? [];

        return self::renderNodes($content);
    }

    /**
     * Convenience wrapper that accepts a raw JSON string.
     */
    public static function renderJson(?string $json): string
    {
        if ($json === null || $json === '') {
            return '';
        }

        $decoded = json_decode($json, true);

        return self::render(is_array($decoded) ? $decoded : null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function renderNodes(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= self::renderNode($node);
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function renderNode(array $node): string
    {
        $type = $node['type'] ?? 'text';
        $attrs = $node['attrs'] ?? [];
        $content = self::renderNodes($node['content'] ?? []);
        $text = $node['text'] ?? '';

        return match ($type) {
            'paragraph' => self::wrapInline('<p>'.$content.'</p>', $node),
            'heading' => self::wrapInline('<h'.($attrs['level'] ?? 1).'>'.$content.'</h'.($attrs['level'] ?? 1).'>', $node),
            'bulletList' => '<ul>'.$content.'</ul>',
            'orderedList' => '<ol>'.$content.'</ol>',
            'listItem' => '<li>'.$content.'</li>',
            'blockquote' => '<blockquote>'.$content.'</blockquote>',
            'codeBlock' => '<pre><code>'.htmlspecialchars(implode('', array_map(
                fn ($n) => $n['text'] ?? '',
                $node['content'] ?? [],
            )), ENT_QUOTES).'</code></pre>',
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'table' => '<table><tbody>'.$content.'</tbody></table>',
            'tableRow' => '<tr>'.$content.'</tr>',
            'tableHeader' => '<th>'.$content.'</th>',
            'tableCell' => '<td>'.$content.'</td>',
            'image' => self::renderImage($attrs),
            'placeholder' => self::renderPlaceholder($node),
            'text' => self::renderText($text ?? '', $node['marks'] ?? []),
            default => self::wrapUnknown($content, $type),
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private static function renderImage(array $attrs): string
    {
        $src = $attrs['src'] ?? '';
        $alt = $attrs['alt'] ?? '';
        $layout = $attrs['layout'] ?? 'block';
        $width = $attrs['width'] ?? null;
        $height = $attrs['height'] ?? null;

        $style = '';
        if ($width !== null && $width !== '') {
            $style .= 'width:'.intval($width).'px;';
        }
        if ($height !== null && $height !== '') {
            $style .= 'height:'.intval($height).'px;';
        }

        return '<img src="'.htmlspecialchars($src, ENT_QUOTES).'" alt="'.htmlspecialchars((string) $alt, ENT_QUOTES).'" data-layout="'.htmlspecialchars((string) $layout, ENT_QUOTES).'"'.($style !== '' ? ' style="'.$style.'"' : '').'>';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function renderPlaceholder(array $node): string
    {
        $attrs = $node['attrs'] ?? [];

        // The placeholder token's text (e.g. "occupant_name") is rendered as a
        // visible {{token}} so the LeaseAgreementTemplate merge can substitute
        // it later. Unknown/free text falls through to its literal value.
        $value = $attrs['token'] ?? ($node['text'] ?? '');

        if ($value === '') {
            return '';
        }

        return '{{'.htmlspecialchars((string) $value, ENT_QUOTES).'}}';
    }

    private static function wrapInline(string $html, array $node): string
    {
        $attrs = $node['attrs'] ?? [];

        // Preserve alignment set by the TextAlign extension.
        $align = $attrs['textAlign'] ?? null;

        if ($align) {
            return str_replace('>', ' style="text-align:'.$align.';">', $html, 1);
        }

        return $html;
    }

    private static function wrapUnknown(string $content, string $type): string
    {
        // Unknown nodes (e.g. custom block attrs we don't otherwise map) render
        // their children inline so nothing is silently dropped.
        return $content !== '' ? $content : '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $marks
     */
    private static function renderText(string $text, array $marks): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES);

        foreach ($marks as $mark) {
            $html = self::applyMark($html, $mark);
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private static function applyMark(string $html, array $mark): string
    {
        $type = $mark['type'];
        $attrs = $mark['attrs'] ?? [];

        return match ($type) {
            'bold' => '<strong>'.$html.'</strong>',
            'italic' => '<em>'.$html.'</em>',
            'strike' => '<s>'.$html.'</s>',
            'underline' => '<u>'.$html.'</u>',
            'code' => '<code>'.$html.'</code>',
            'subscript', 'sub' => '<sub>'.$html.'</sub>',
            'superscript', 'sup' => '<sup>'.$html.'</sup>',
            'link' => '<a href="'.htmlspecialchars($attrs['href'] ?? '', ENT_QUOTES).'"'.(($attrs['target'] ?? null) === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.$html.'</a>',
            'textStyle' => self::applyTextStyle($html, $attrs),
            default => $html,
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private static function applyTextStyle(string $html, array $attrs): string
    {
        $styles = [];

        if (isset($attrs['fontSize']) && $attrs['fontSize'] !== null && $attrs['fontSize'] !== '') {
            $styles[] = 'font-size:'.htmlspecialchars((string) $attrs['fontSize'], ENT_QUOTES).';';
        }
        if (isset($attrs['fontFamily']) && $attrs['fontFamily'] !== null && $attrs['fontFamily'] !== '') {
            $styles[] = 'font-family:'.htmlspecialchars((string) $attrs['fontFamily'], ENT_QUOTES).';';
        }
        if (isset($attrs['color']) && $attrs['color'] !== '') {
            $styles[] = 'color:'.htmlspecialchars((string) $attrs['color'], ENT_QUOTES).';';
        }
        if (isset($attrs['backgroundColor']) && $attrs['backgroundColor'] !== '') {
            $styles[] = 'background-color:'.htmlspecialchars((string) $attrs['backgroundColor'], ENT_QUOTES).';';
        }

        if ($styles === []) {
            return $html;
        }

        return '<span style="'.implode(' ', $styles).'">'.$html.'</span>';
    }
}
