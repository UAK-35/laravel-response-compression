<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * The changelog read for the claim its preamble makes about the versions it does not own.
 *
 * WHY THIS EXISTS
 * ---------------
 * `CHANGELOG.md` opens by saying that the versions count on from the newest tag, and that the
 * history inherited from the upstream is outside that sequence. The claim is prose, it sits above
 * the first `##` heading, and it makes itself true by naming numbers — which is exactly what makes
 * it go stale. The day this package reaches `0.1.0` itself, the file gains a `## [v0.1.0]` section
 * while the preamble still says that number belongs to someone else. Every other guard here reads
 * keys, links or source; nothing reads the opening paragraph, so nothing would notice.
 *
 * WHAT A SECTION IS, AND IS NOT
 * -----------------------------
 * A version is a section only as a **second-level** heading. The inherited `0.1.0` sits under
 * `## Inherited from upstream` as a third-level heading, which is the whole point of that fold: the
 * number stays in the file, stays readable, stays searchable, and is not one of this repository's
 * releases. The level is what tells the two apart, and the level is also how the preamble is
 * written — the numbers it names are the ones at the deeper level, and they must stay there.
 *
 * The shape a version heading is read by is the shape `documentedVersions()` in `bin/release.php`
 * reads it by, and it is written here rather than imported: the script is a program that runs on
 * include, so a test cannot call into it, and what belongs beside the document guards is a reader
 * of the document rather than a copy of the script's decisions.
 */
final class ChangelogPreamble
{
    /**
     * The versions the preamble names, and the versions the file has a section for.
     *
     * The preamble is everything above the first `##` heading — the paragraph a reader meets before
     * any release. The claim is made there and nowhere else, so a number written below it, in a
     * released section or in the inherited history or in the notes, is not read as part of it.
     *
     * @return array{preamble: list<string>, sections: list<string>}
     *
     * @throws RuntimeException when the file has no `##` heading, so there is no preamble to read
     */
    public static function read(string $content): array
    {
        if (preg_match('/^##[ \t]/m', $content, $heading, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('The changelog has no `##` heading: there is no preamble to read, and no section to read it against.');
        }

        return [
            'preamble' => self::named(substr($content, 0, $heading[0][1])),
            'sections' => self::released($content),
        ];
    }

    /**
     * The versions the preamble names that the file also has a section for.
     *
     * This is the disagreement itself: a number written down as someone else's, and released here.
     *
     * @return list<string>
     */
    public static function stale(string $content): array
    {
        $read = self::read($content);

        return array_values(array_intersect($read['preamble'], $read['sections']));
    }

    /**
     * Every `X.Y.Z` a piece of prose names, in the order it names them.
     *
     * Backticked or not, and with or without the `v` the tags carry: the claim is made in prose, and
     * a number in prose is a number to whoever reads it. The `v` comes off so that the two spellings
     * of one version compare equal.
     *
     * @return list<string>
     */
    private static function named(string $prose): array
    {
        preg_match_all('/v?\d+\.\d+\.\d+/', $prose, $matches);

        return array_values(array_unique(array_map(
            static fn (string $version): string => ltrim($version, 'v'),
            $matches[0],
        )));
    }

    /**
     * The versions the file announces as second-level headings — the shape its own releases have.
     *
     * A heading naming a prerelease of a version counts as a section for that version: the line is
     * this repository's from the first alpha of it, which is the moment the preamble's claim stops
     * being true.
     *
     * @return list<string>
     */
    private static function released(string $content): array
    {
        preg_match_all('/^##[ \t]+\[?v?(\d+\.\d+\.\d+)/m', $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
