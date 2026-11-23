<?php

namespace App\Support;

use Com\Tecnick\Pdf\Parser\Parser;
use RuntimeException;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Rewrite a PDF with a classic cross-reference table, so that the free parser of FPDI can read it.
 *
 * The free parser of FPDI only reads PDF files whose cross-reference is a table: those whose cross-reference is a
 * compressed stream (PDF 1.5+, e.g. the S-13-E saved by the Adobe PDF Library) are rewritten, with every object (those
 * kept in object streams included) written on its own, and their streams copied as they are (still compressed).
 */
class ClassicPdf
{
    /**
     * The PDF itself if FPDI can read it, else its rewriting with a classic cross-reference table.
     */
    public static function from(string $contents): string
    {
        return self::isReadable($contents) ? $contents : self::rewrite($contents);
    }

    /**
     * Whether the free parser of FPDI can read the cross-reference of a PDF.
     */
    public static function isReadable(string $contents): bool
    {
        try {
            (new PdfParser(StreamReader::createByString($contents)))->getCrossReference();

            return true;
        } catch (CrossReferenceException $exception) {
            return $exception->getCode() !== CrossReferenceException::COMPRESSED_XREF;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Rewrite a PDF with a classic cross-reference table.
     *
     * @throws RuntimeException When the PDF cannot be read
     */
    public static function rewrite(string $contents): string
    {
        try {
            [$xref, $objects] = (new Parser(['decode_streams' => false]))->parse($contents);
        } catch (Throwable $exception) {
            throw new RuntimeException('The PDF cannot be read: '.$exception->getMessage(), previous: $exception);
        }

        $trailer = $xref['trailer'] ?? [];

        if (empty($trailer['root'])) {
            throw new RuntimeException('The PDF has no catalog.');
        }

        if (! empty($trailer['encrypt'])) {
            throw new RuntimeException('The PDF is encrypted.');
        }

        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $key => $tokens) {
            [$number, $generation] = array_map('intval', explode('_', (string) $key));

            if ($number <= 0 || $tokens === [] || self::isStructural($tokens[0])) {
                continue;
            }

            $offsets[$number] = [strlen($pdf), $generation];
            $pdf .= "{$number} {$generation} obj\n".self::writeObject($tokens)."\nendobj\n";
        }

        ksort($offsets);
        $size = ($offsets === [] ? 0 : max(array_keys($offsets))) + 1;
        $table = "0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $table .= isset($offsets[$number])
                ? sprintf("%010d %05d n \n", $offsets[$number][0], $offsets[$number][1])
                : "0000000000 00000 f \n";
        }

        $startXref = strlen($pdf);
        $trailerEntries = '/Size '.$size.' /Root '.self::reference($trailer['root']);

        if (! empty($trailer['info'])) {
            $trailerEntries .= ' /Info '.self::reference($trailer['info']);
        }

        if (! empty($trailer['id']) && count($trailer['id']) === 2) {
            $trailerEntries .= ' /ID [<'.$trailer['id'][0].'> <'.$trailer['id'][1].'>]';
        }

        return $pdf."xref\n0 {$size}\n{$table}trailer\n<< {$trailerEntries} >>\nstartxref\n{$startXref}\n%%EOF\n";
    }

    /**
     * Whether an object only describes how the original file is laid out (object streams, cross-reference streams,
     * linearization), and has no place in the rewritten one.
     *
     * @param  array{0: string, 1: mixed, 2?: int}  $token
     */
    protected static function isStructural(array $token): bool
    {
        if ($token[0] !== '<<' || ! is_array($token[1])) {
            return false;
        }

        $entries = self::dictionaryEntries($token[1]);

        return isset($entries['Linearized'])
            || in_array($entries['Type'][1] ?? null, ['ObjStm', 'XRef'], true);
    }

    /**
     * Write the tokens of an indirect object: a value, possibly followed by its stream.
     *
     * @param  list<array{0: string, 1: mixed, 2?: int}>  $tokens
     */
    protected static function writeObject(array $tokens): string
    {
        $streamIndex = null;

        foreach ($tokens as $index => $token) {
            if ($token[0] === 'stream') {
                $streamIndex = $index;
            }
        }

        if ($streamIndex === null) {
            return implode(' ', array_map(self::write(...), array_filter($tokens, fn (array $token): bool => $token[0] !== 'endobj')));
        }

        $data = (string) $tokens[$streamIndex][1];
        $dictionary = $tokens[$streamIndex - 1] ?? ['<<', []];

        return self::writeDictionary($dictionary[1], ['Length' => (string) strlen($data)])
            ."\nstream\n".$data."\nendstream";
    }

    /**
     * Write a token, and the tokens nested in it.
     *
     * @param  array{0: string, 1: mixed, 2?: int}  $token
     */
    protected static function write(array $token): string
    {
        [$type, $value] = $token;

        return match ($type) {
            '/' => '/'.$value,
            '(' => '('.$value.')',
            '<' => '<'.$value.'>',
            '[' => '['.implode(' ', array_map(self::write(...), $value)).']',
            '<<' => self::writeDictionary($value),
            'objref' => self::reference((string) $value),
            'numeric', 'boolean' => (string) $value,
            'null' => 'null',
            default => throw new RuntimeException("Unexpected PDF token [{$type}]."),
        };
    }

    /**
     * Write a dictionary, replacing the values of some of its keys.
     *
     * @param  list<array{0: string, 1: mixed, 2?: int}>  $tokens
     * @param  array<string, string>  $replacements  Written values, keyed by name
     */
    protected static function writeDictionary(array $tokens, array $replacements = []): string
    {
        $written = [];

        foreach (self::dictionaryEntries($tokens) as $name => $value) {
            $written[] = '/'.$name.' '.($replacements[$name] ?? self::write($value));
            unset($replacements[$name]);
        }

        foreach ($replacements as $name => $value) {
            $written[] = '/'.$name.' '.$value;
        }

        return '<<'.implode(' ', $written).'>>';
    }

    /**
     * The entries of a dictionary, keyed by name.
     *
     * @param  list<array{0: string, 1: mixed, 2?: int}>  $tokens
     * @return array<string, array{0: string, 1: mixed, 2?: int}>
     */
    protected static function dictionaryEntries(array $tokens): array
    {
        $entries = [];

        for ($index = 0; $index + 1 < count($tokens); $index += 2) {
            if ($tokens[$index][0] === '/') {
                $entries[(string) $tokens[$index][1]] = $tokens[$index + 1];
            }
        }

        return $entries;
    }

    /**
     * Write a reference to an indirect object, given as "number_generation".
     */
    protected static function reference(string $key): string
    {
        [$number, $generation] = explode('_', $key) + [1 => '0'];

        return "{$number} {$generation} R";
    }
}
