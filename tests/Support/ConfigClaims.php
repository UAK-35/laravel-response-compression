<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * The claims this package's documents make about which config keys are read.
 *
 * WHY THIS EXISTS
 * ---------------
 * `ConfigKeys` compares the keys the config publishes with the keys the source reads, and
 * that comparison is blind to the third place a key is described: the prose. This package's
 * own history is the case for it — `enable_logging` was published, read by nothing, and its
 * docblock in the config file and in the README both said it was inert. The claim was true
 * when it was written, the wiring made it false, and nothing noticed: the guard that compares
 * the two sets cannot see a comment, so the comment stayed wrong until it was found by
 * reading and grepping. A key whose documentation says it is unread while the source reads it
 * is the same defect as a key and a reader disagreeing, one layer up.
 *
 * So a claim is read here, in two scopes, and matched against the read set. A sentence that
 * carries a marker and no resolution is the unit in both; what differs is only how the key is
 * known:
 *
 *   positional  the docblock beside a key, in the config file and in the README's copy of it.
 *               The key is the position rather than a word, because this is where the claim
 *               lives: "Not yet wired." names nothing and is about the key beneath it.
 *   prose       a sentence in the README or a design record. Here the key has to name itself,
 *               in a code span (`min_length`) or as its path (`response-compression.min_length`),
 *               which is how every document in this package writes one — and it is what keeps
 *               a sentence about "the level of compression" from being read as a claim about
 *               `br.level`.
 *
 * WHAT IS NOT A CLAIM
 * -------------------
 * A record of what a key *used to be* is not a claim that it still is. This package writes
 * that history the way anyone does — "read by nobody, until the middleware was wired to it" —
 * so a sentence carrying a resolution (`until`, `no longer`, `has since`) is read as an
 * account rather than as a claim. Without that, the honest sentence about the fix would be
 * reported as the defect it describes.
 *
 * The CHANGELOG is deliberately not read. It is a record of the past by construction, so every
 * claim in it is history; a surface this reader had to be told not to read would be a rule
 * that exists only to be worked around.
 *
 * @guards-index reading
 */
final class ConfigClaims
{
    /** The heading whose fence is the README's copy of the config file. */
    public const string README_CONFIG_HEADING = '## Config';

    /** The phrases that claim a key is unread, in the present tense. */
    public const array MARKERS = [
        'nothing reads',
        'read by nobody',
        'read by no one',
        'has no reader',
        'no reader',
        'is not read',
        'are not read',
        'is never read',
        'never read',
        'not yet wired',
        'is unwired',
        'still unwired',
        'remains unwired',
        'is inert',
        'are inert',
        'goes unread',
    ];

    /**
     * The cues that make a sentence an account of the past rather than a claim about the
     * present. Every one of them is how this package phrases the wiring of a key, so a sentence
     * carrying one is read as the history it is — the sentence that describes this guard
     * included, which names the key whose docblock went stale and says the middleware had been
     * wired to read it. The wiring is in that sentence, so it accounts for itself.
     *
     * The cost is stated rather than left implicit: a live claim worded with a cue — "nothing
     * reads `x` and nothing has been wired yet" — is read as history and missed. That is the
     * price of not reporting the record that explains a fix as the defect it describes.
     */
    public const array RESOLUTIONS = [
        'until',
        'has since',
        'since been',
        'no longer',
        'used to',
        'was wired',
        'were wired',
        'been wired',
        'was read',
        'were read',
        'anymore',
    ];

    /**
     * The claims carried by the docblock beside each published key.
     *
     * @param  array<string, string>  $docblocks  as `ConfigDoc::shipped()` and `documented()` read them
     * @param  list<string>  $published
     * @return list<array{key: string, claim: string, where: string}>
     */
    public static function docblocks(array $docblocks, string $document, array $published): array
    {
        $claims = [];

        foreach ($published as $key) {
            $marker = self::claiming($docblocks[$key] ?? '');

            if ($marker === null) {
                continue;
            }

            $claims[] = [
                'key' => $key,
                'claim' => $marker,
                'where' => sprintf('%s, the docblock beside `%s`', $document, $key),
            ];
        }

        return $claims;
    }

    /**
     * The claims carried by a document read as prose.
     *
     * The line a claim is on is the line its sentence starts on, counted in the file as it was
     * written rather than in the sentence as it was read.
     *
     * @param  list<string>  $published
     * @return list<array{key: string, claim: string, where: string}>
     */
    public static function prose(string $text, string $document, array $published): array
    {
        $claims = [];

        foreach (self::sentences($text) as ['text' => $sentence, 'offset' => $offset]) {
            if (self::resolved($sentence)) {
                continue;
            }

            $marker = self::marker($sentence);

            if ($marker === null) {
                continue;
            }

            foreach ($published as $key) {
                if (! self::names($sentence, $key)) {
                    continue;
                }

                $claims[] = [
                    'key' => $key,
                    'claim' => $marker,
                    'where' => sprintf('%s:%d', $document, substr_count(substr($text, 0, $offset), "\n") + 1),
                ];
            }
        }

        return $claims;
    }

    /**
     * Every claim the package's documents make: the two docblock scopes and every record.
     *
     * @param  list<string>  $published
     * @return list<array{key: string, claim: string, where: string}>
     */
    public static function all(string $root, array $published): array
    {
        $claims = [
            ...self::docblocks(
                ConfigDoc::shipped($root.'/config/response-compression.php')['docblocks'],
                'config/response-compression.php',
                $published,
            ),
            ...self::docblocks(
                ConfigDoc::documented($root.'/README.md', self::README_CONFIG_HEADING)['docblocks'],
                'README.md',
                $published,
            ),
        ];

        foreach (self::documents($root) as $document) {
            $text = @file_get_contents($document);

            if ($text === false) {
                continue;
            }

            $claims = [...$claims, ...self::prose($text, Docs::relative($root, $document), $published)];
        }

        return $claims;
    }

    /**
     * The documents read as prose: the README, and each design record under `docs/`.
     *
     * @return list<string>
     */
    private static function documents(string $root): array
    {
        return array_values(array_filter(
            [$root.'/README.md', ...(glob($root.'/docs/*.md') ?: [])],
            is_file(...),
        ));
    }

    /**
     * The phrase a text claims with, or null when it claims nothing: the first sentence that
     * carries a marker and does not account for the claim having stopped being true.
     */
    private static function claiming(string $text): ?string
    {
        foreach (self::sentences($text) as ['text' => $sentence]) {
            if (self::resolved($sentence)) {
                continue;
            }

            $marker = self::marker($sentence);

            if ($marker !== null) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * A text's sentences, with the offset each one starts at.
     *
     * A sentence is read whole rather than line by line, because the markdown here wraps at
     * ninety characters: the key and the claim about it are routinely on different lines, and a
     * line-based scan reads "`enable_logging` was inert, its" as a sentence that says nothing.
     *
     * A period inside a code span does not end one. Keys here contain them — `br.level`,
     * `response-compression.min_length` — and splitting a name in half takes it out of the
     * sentence that makes a claim about it, so the claim is then one this reader never sees.
     * The offset points at the sentence's first character rather than at the whitespace before
     * it, so the line reported is the line the sentence begins on.
     *
     * The text comes back with its whitespace collapsed, because a sentence here is prose that
     * happens to be wrapped at ninety characters: "nothing\nreads this key" is one claim, and
     * matching phrases against the text as the file wrapped it would see no marker in it at all.
     *
     * @return list<array{text: string, offset: int}>
     */
    private static function sentences(string $text): array
    {
        $sentences = [];
        $sentence = '';
        $start = null;
        $span = false;
        $length = strlen($text);

        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];

            if ($character === '`') {
                $span = ! $span;
            }

            if (! $span && str_contains('.!?', $character)) {
                if (trim($sentence) !== '') {
                    $sentences[] = ['text' => self::collapse($sentence), 'offset' => $start ?? $index];
                }

                $sentence = '';
                $start = null;

                continue;
            }

            if ($start === null && trim($character) !== '') {
                $start = $index;
            }

            $sentence .= $character;
        }

        if (trim($sentence) !== '') {
            $sentences[] = ['text' => self::collapse($sentence), 'offset' => $start ?? 0];
        }

        return $sentences;
    }

    /**
     * A sentence as one line: the whitespace a wrap put in the middle of it taken out, so a
     * phrase that spans the wrap reads as the phrase it was written as.
     */
    private static function collapse(string $sentence): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($sentence));
    }

    /**
     * The phrase a piece of text claims with, or null when it claims nothing.
     */
    private static function marker(string $text): ?string
    {
        $text = strtolower($text);

        foreach (self::MARKERS as $marker) {
            if (str_contains($text, $marker)) {
                return $marker;
            }
        }

        // The one marker with a word boundary to keep: `unreadable` is this package's word for
        // a value that cannot be read, which is the opposite of a key that nothing reads.
        return preg_match('/\bunread\b/', $text) === 1 ? 'unread' : null;
    }

    /**
     * Whether the text accounts for the claim having stopped being true.
     */
    private static function resolved(string $text): bool
    {
        $text = strtolower($text);

        return array_any(self::RESOLUTIONS, static fn (string $cue): bool => str_contains($text, $cue));
    }

    /**
     * Whether a sentence names a key: in the code span this package writes one in, or as its
     * fully qualified path.
     */
    private static function names(string $sentence, string $key): bool
    {
        return str_contains($sentence, '`'.$key.'`')
            || str_contains($sentence, ConfigKeys::NAMESPACE.'.'.$key);
    }
}
