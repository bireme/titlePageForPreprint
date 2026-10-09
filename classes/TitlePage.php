<?php

namespace APP\plugins\generic\titlePageForPreprint\classes;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use TCPDF_FONTS;
use TCPDF;
use Normalizer;
use APP\plugins\generic\titlePageForPreprint\classes\Pdf;
use APP\plugins\generic\titlePageForPreprint\classes\TitlePageRequirements;

class TitlePage
{
    private $submission;
    private $checklist;
    private $locale;
    private $fontName;
    private $titlePageRequirements;

    public const OUTPUT_DIRECTORY = DIRECTORY_SEPARATOR . "tmp" .  DIRECTORY_SEPARATOR;
    private const ORIGINAL_FILE_COPY = self::OUTPUT_DIRECTORY . "original_file_copy.pdf";
    private const AUX_FILE = self::OUTPUT_DIRECTORY . "aux_file.pdf";

    // A4 portrait measurements in mm; retain the reference margins and logo proportions.
    private const PAGE_WIDTH = 210;
    private const PAGE_HEIGHT = 297;
    private const MARGIN_X = 22.86;
    private const MARGIN_Y = 19.05;
    private const CONTENT_WIDTH = self::PAGE_WIDTH - 2 * self::MARGIN_X;
    private const LOGO_LEFT_WIDTH = 76.90;
    private const LOGO_RIGHT_WIDTH = 73.66;
    private const LOGO_RIGHT_HEIGHT = 30.64;
    private const VERSION_Y = 58.5;
    private const DATE_COLUMN_GAP = 12;
    private const DATE_COLUMN_OFFSET = (self::CONTENT_WIDTH + self::DATE_COLUMN_GAP) / 2;
    private const AUTHORS_BLOCK_THRESHOLD = 6;
    private const BLUE = [0, 80, 141];
    private const ORANGE = [180, 60, 11];
    // TCPDF bundles all three Unicode styles; the supplied Open Sans is regular only.
    private const COVER_FONT = 'dejavusans';
    private const SPACING = [
        'afterVersion' => 10.23,
        'beforeSubtitle' => 3.53,
        'beforeAuthors' => 7.76,
        'betweenAuthors' => 1.41,
        'beforeDoi' => 9.88,
        'beforeDisclaimer' => 13.40,
        'betweenNotices' => 3.53,
        'beforeDates' => 14.82,
        'beforeDateValue' => 2.82,
    ];
    private const LAYOUTS = [
        ['gap' => 1.0, 'title' => 29, 'subtitle' => 17, 'author' => 12, 'warning' => 15, 'body' => 11.5],
        ['gap' => 0.65, 'title' => 26, 'subtitle' => 15, 'author' => 11, 'warning' => 14, 'body' => 11],
        ['gap' => 0.4, 'title' => 22, 'subtitle' => 13, 'author' => 10, 'warning' => 12, 'body' => 10],
        ['gap' => 0.2, 'title' => 18, 'subtitle' => 11, 'author' => 9, 'warning' => 11, 'body' => 9],
    ];

    public function __construct(SubmissionModel $submission, array $checklist, string $locale)
    {
        $this->submission = $submission;
        $this->checklist = $checklist;
        $this->locale = $locale;
        $this->fontName = TCPDF_FONTS::addTTFfont(__DIR__ . '/../resources/opensans.ttf', 'TrueTypeUnicode', '', 32);
        $this->titlePageRequirements = new TitlePageRequirements();
    }

    public function removeTitlePage($pdf): void
    {
        $separateCommand = "cpdf {$pdf} 2-end -o {$pdf}";
        exec($separateCommand, $output, $resultCode);

        if ($resultCode != 0) {
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.requirements.removeTitlePageMissing');
            throw new \Exception('Title Page Remove Failure');
        }
    }

    private function coverText(string $key, array $params = []): string
    {
        return __('plugins.generic.titlePageForPreprint.cover.' . $key, $params, $this->locale);
    }

    private function renderText(TCPDF $pdf, string $text, float $size, string $style = '', string $align = 'C', array $color = [0, 0, 0]): void
    {
        $pdf->SetFont(self::COVER_FONT, $style, $size);
        $pdf->SetTextColor(...$color);
        $pdf->SetX(self::MARGIN_X);
        $pdf->MultiCell(self::CONTENT_WIDTH, 0, $text, 0, $align, false, 1);
    }

    private function renderLogos(TCPDF $pdf): void
    {
        $resources = dirname(__DIR__) . '/resources/';
        foreach (['lilacs-logo.jpg', 'lilacs-preprint-logo.png'] as $asset) {
            if (!is_readable($resources . $asset)) {
                throw new \RuntimeException('Missing institutional logo: ' . $asset);
            }
        }
        // Align the image boxes along their bottom edge, preserving the original aspect ratios.
        $leftHeight = self::LOGO_LEFT_WIDTH * 145 / 486;
        $bottom = self::MARGIN_Y + self::LOGO_RIGHT_HEIGHT;
        $pdf->Image($resources . 'lilacs-logo.jpg', self::MARGIN_X, $bottom - $leftHeight, self::LOGO_LEFT_WIDTH, 0, 'JPG');
        $pdf->Image($resources . 'lilacs-preprint-logo.png', self::PAGE_WIDTH - self::MARGIN_X - self::LOGO_RIGHT_WIDTH, self::MARGIN_Y, self::LOGO_RIGHT_WIDTH, 0, 'PNG');
    }

    private function renderPreprintVersion(TCPDF $pdf): void
    {
        $escape = static fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pdf->SetXY(self::MARGIN_X, self::VERSION_Y);
        $pdf->SetFont(self::COVER_FONT, '', 14);
        $html = '<span style="font-size:14pt;color:#00508D;font-weight:bold">' . $escape($this->coverText('preprint')) . '</span>'
            . ' &nbsp; <span style="font-size:10pt;color:#B43C0B;font-weight:bold">'
            . $escape($this->coverText('version', ['version' => $this->submission->getVersion()])) . '</span>';
        $pdf->writeHTMLCell(self::CONTENT_WIDTH, 0, '', '', $html, 0, 1, false, true, 'C');
    }

    private function renderTitle(TCPDF $pdf, array $layout): void
    {
        $title = Normalizer::normalize($this->submission->getTitle($this->locale));
        $this->renderText($pdf, $title, $layout['title'], 'B');
        $subtitle = trim($this->submission->getSubtitle($this->locale));
        if ($subtitle !== '') {
            $pdf->SetY($pdf->GetY() + self::SPACING['beforeSubtitle'] * $layout['gap']);
            $this->renderText($pdf, Normalizer::normalize($subtitle), $layout['subtitle'], 'I');
        }
    }

    private function renderAuthors(TCPDF $pdf, array $layout): void
    {
        $authors = $this->submission->getAuthorNames($this->locale);
        if (count($authors) >= self::AUTHORS_BLOCK_THRESHOLD) {
            $this->renderText($pdf, implode(', ', $authors), $layout['author']);
            return;
        }
        foreach ($authors as $index => $author) {
            if ($index > 0) {
                $pdf->SetY($pdf->GetY() + self::SPACING['betweenAuthors'] * $layout['gap']);
            }
            $this->renderText($pdf, $author, $layout['author']);
        }
    }

    private function renderDoi(TCPDF $pdf): void
    {
        $doi = $this->submission->getDOI();
        $escape = static fn ($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pdf->SetFont(self::COVER_FONT, '', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX(self::MARGIN_X);
        $html = '<span style="font-size:10pt;color:#00508D;font-weight:bold">' . $escape($this->coverText('doi')) . '</span> &nbsp; ';
        if ($doi !== '') {
            $url = preg_match('~^https?://(?:dx\.)?doi\.org/~i', $doi) ? $doi : 'https://doi.org/' . $doi;
            $html .= '<a href="' . $escape($url) . '" style="color:#000000;text-decoration:none">' . $escape($doi) . '</a>';
        } else {
            $html .= $escape($this->coverText('doiUnavailable'));
        }
        $pdf->writeHTMLCell(self::CONTENT_WIDTH, 0, '', '', $html, 0, 1, false, true, 'C');
    }

    private function renderDisclaimer(TCPDF $pdf, array $layout): void
    {
        $this->renderText($pdf, $this->coverText('notPeerReviewed'), $layout['warning'], 'B', 'L', self::ORANGE);
        $pdf->SetY($pdf->GetY() + self::SPACING['betweenNotices'] * $layout['gap']);
        $this->renderText($pdf, $this->coverText('disclaimer'), $layout['body'], 'I', 'L');
    }

    private function renderDates(TCPDF $pdf, array $layout): void
    {
        $top = $pdf->GetY();
        $width = self::CONTENT_WIDTH - self::DATE_COLUMN_OFFSET;
        $columns = [
            [$this->coverText('submitted'), $this->submission->getSubmissionDate()],
            [$this->coverText('posted'), $this->coverText('postedValue', [
                'date' => $this->submission->getPublicationDate(),
                'version' => $this->submission->getVersion(),
            ])],
        ];
        $bottom = $top;
        foreach ($columns as $index => [$label, $value]) {
            $x = self::MARGIN_X + $index * self::DATE_COLUMN_OFFSET;
            $pdf->SetXY($x, $top);
            $pdf->SetFont(self::COVER_FONT, 'B', 9);
            $pdf->SetTextColor(...self::BLUE);
            $pdf->MultiCell($width, 0, $label, 0, 'L', false, 1);
            $pdf->SetXY($x, $pdf->GetY() + self::SPACING['beforeDateValue'] * $layout['gap']);
            $pdf->SetFont(self::COVER_FONT, '', $layout['author']);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell($width, 0, $value, 0, 'L', false, 1);
            $bottom = max($bottom, $pdf->GetY());
        }
        $pdf->SetY($bottom);
    }

    private function generateTitlePage(): string
    {
        try {
            foreach (self::LAYOUTS as $layout) {
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(self::MARGIN_X, self::MARGIN_Y, self::MARGIN_X);
                $pdf->SetAutoPageBreak(false, self::MARGIN_Y);
                $pdf->setCellPaddings(0, 0, 0, 0);
                $pdf->setCellHeightRatio(1.15);
                $pdf->AddPage();
                $this->renderLogos($pdf);
                $this->renderPreprintVersion($pdf);
                $pdf->SetY($pdf->GetY() + self::SPACING['afterVersion'] * $layout['gap']);
                $this->renderTitle($pdf, $layout);
                $pdf->SetY($pdf->GetY() + self::SPACING['beforeAuthors'] * $layout['gap']);
                $this->renderAuthors($pdf, $layout);
                $pdf->SetY($pdf->GetY() + self::SPACING['beforeDoi'] * $layout['gap']);
                $this->renderDoi($pdf);
                $pdf->SetY($pdf->GetY() + self::SPACING['beforeDisclaimer'] * $layout['gap']);
                $this->renderDisclaimer($pdf, $layout);
                $pdf->SetY($pdf->GetY() + self::SPACING['beforeDates'] * $layout['gap']);
                $this->renderDates($pdf, $layout);

                if ($pdf->GetY() <= self::PAGE_HEIGHT - self::MARGIN_Y && $pdf->getNumPages() === 1) {
                    $file = self::OUTPUT_DIRECTORY . 'titlePage.pdf';
                    $pdf->Output($file, 'F');
                    return $file;
                }
            }
            // Never truncate metadata or replace the original with an overflowing cover.
            throw new \LengthException($this->coverText('tooLong'));
        } catch (\Exception $e) {
            $key = $e instanceof \LengthException ? 'cover.tooLong' : 'requirements.generateTitlePageMissing';
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.' . $key);
            throw $e;
        }
    }

    public function generateChecklistPage(): string
    {
        try {
            $checklistPage = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $checklistPage->setPrintHeader(false);
            $checklistPage->setPrintFooter(false);
            $checklistPage->AddPage();

            $checklistPage->Write(0, __('plugins.generic.titlePageForPreprint.checklistLabel', [], $this->locale) . ": ", '', 0, 'JUSTIFY', true, 0, false, false, 0);
            $checklistPage->SetFont($this->fontName, '', 10, '', false);
            $checklistPage->Ln(5);

            $checklistText = '';
            foreach ($this->checklist[$this->locale] as $item) {
                $checklistText = $checklistText . "<ul style=\"text-align:justify;\"><li>" . $item . "</li></ul>";
            }
            $checklistPage->writeHTMLCell(0, 0, '', '', $checklistText, 1, 1, false, true, 'JUSTIFY', false);
            $checklistPage->SetFont($this->fontName, '', 11, '', false);
            $checklistPage->Ln(5);

            $checklistPageFile = self::OUTPUT_DIRECTORY . 'checklistPage.pdf';
            $checklistPage->Output($checklistPageFile, 'F');
        } catch (Exception $e) {
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.requirements.generateChecklistPageMissing');
            throw new Exception('Checklist Page Generation Failure');
        }

        return $checklistPageFile;
    }

    public function addDocumentHeader($pdf): void
    {
        $headerText = __('plugins.generic.titlePageForPreprint.headerText', [], $this->locale);
        $addHeaderCommand = "cpdf -add-text \"{$headerText}\" -top 15pt -font \"Helvetica\" -font-size 8 {$pdf} -o " . self::AUX_FILE;
        exec($addHeaderCommand, $output, $resultCode);
        rename(self::AUX_FILE, $pdf);

        if ($resultCode != 0) {
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.requirements.addDocumentHeaderMissing');
            throw new Exception('Headers Stamping Failure');
        }
    }

    private function concatenateTitlePage($pdf, $titlePage): void
    {
        $uniteCommand = "cpdf -merge {$titlePage} {$pdf} -o " . self::AUX_FILE;
        exec($uniteCommand, $output, $resultCode);
        rename(self::AUX_FILE, $pdf);

        if ($resultCode != 0) {
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.requirements.concatenateTitlePageMissing');
            throw new Exception('Title Page Concatenation Failure');
        }
    }

    public function concatenateChecklistPage($pdf, $checklistPage): void
    {
        $uniteCommand = "cpdf -merge {$pdf} {$checklistPage} -o " . self::AUX_FILE;
        exec($uniteCommand, $output, $resultCode);
        rename(self::AUX_FILE, $pdf);

        if ($resultCode != 0) {
            $this->titlePageRequirements->showMissingRequirementNotification('plugins.generic.titlePageForPreprint.requirements.concatenateChecklistPageMissing');
            throw new Exception('Checklist Page Concatenation Failure');
        }
    }

    public function insertTitlePageFirstTime(pdf $pdf)
    {
        $originalFile = $pdf->getPath();
        copy($originalFile, self::ORIGINAL_FILE_COPY);

        $this->addDocumentHeader(self::ORIGINAL_FILE_COPY);

        $titlePage = $this->generateTitlePage();
        $this->concatenateTitlePage(self::ORIGINAL_FILE_COPY, $titlePage);

        rename(self::ORIGINAL_FILE_COPY, $originalFile);
    }

    public function updateTitlePage(pdf $pdf)
    {
        $originalFile = $pdf->getPath();
        copy($originalFile, self::ORIGINAL_FILE_COPY);

        $this->removeTitlePage(self::ORIGINAL_FILE_COPY);

        $titlePage = $this->generateTitlePage();
        $this->concatenateTitlePage(self::ORIGINAL_FILE_COPY, $titlePage);

        rename(self::ORIGINAL_FILE_COPY, $originalFile);
    }
}
