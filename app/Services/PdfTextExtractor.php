<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    private static ?bool $pdftotextAvailable = null;

    public function extract(string $disk, string $path): string
    {
        $tempPdf = $this->writeTempPdf($disk, $path);
        $tempText = tempnam(sys_get_temp_dir(), 'pms_text_');

        try {
            if ($this->hasPdftotext()) {
                $process = new Process([
                    'pdftotext',
                    '-layout',
                    '-nopgbrk',
                    $tempPdf,
                    $tempText,
                ]);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('pdftotext failed: '.$process->getErrorOutput());
                }

                return (string) file_get_contents($tempText);
            }

            if (!class_exists(Parser::class)) {
                throw new \RuntimeException('No PDF parser available.');
            }

            $parser = new Parser();
            $pdf = $parser->parseFile($tempPdf);

            return $pdf->getText();
        } finally {
            if (is_file($tempPdf)) {
                @unlink($tempPdf);
            }
            if (is_file($tempText)) {
                @unlink($tempText);
            }
        }
    }

    private function writeTempPdf(string $disk, string $path): string
    {
        $tempPdf = tempnam(sys_get_temp_dir(), 'pms_pdf_');
        $contents = Storage::disk($disk)->get($path);
        file_put_contents($tempPdf, $contents);

        return $tempPdf;
    }

    private function hasPdftotext(): bool
    {
        if (self::$pdftotextAvailable !== null) {
            return self::$pdftotextAvailable;
        }

        $process = new Process(['pdftotext', '-v']);
        $process->run();

        self::$pdftotextAvailable = $process->isSuccessful();

        return self::$pdftotextAvailable;
    }
}
