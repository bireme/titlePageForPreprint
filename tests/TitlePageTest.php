<?php

use APP\plugins\generic\titlePageForPreprint\tests\PdfHandlingTest;
use APP\plugins\generic\titlePageForPreprint\classes\TitlePage;
use APP\plugins\generic\titlePageForPreprint\classes\TitlePageRequirements;
use APP\plugins\generic\titlePageForPreprint\classes\Pdf;

class TitlePageTest extends PdfHandlingTest
{
    private function text(string $path, int $page = 1): string
    {
        $text = shell_exec('pdftotext -f ' . $page . ' -l ' . $page . ' ' . escapeshellarg($path) . ' -');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function coverText(string $key, string $locale, array $params = []): string
    {
        return __('plugins.generic.titlePageForPreprint.cover.' . $key, $params, $locale);
    }

    private function assertPageBounds(string $path): void
    {
        $xml = shell_exec('pdftotext -f 1 -l 1 -bbox-layout ' . escapeshellarg($path) . ' -');
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $page = $doc->getElementsByTagName('page')->item(0);
        $this->assertEqualsWithDelta(612, (float) $page->getAttribute('width'), 0.1);
        $this->assertEqualsWithDelta(792, (float) $page->getAttribute('height'), 0.1);
        foreach ($doc->getElementsByTagName('word') as $word) {
            // TCPDF itself adds a 1 pt attribution at the physical bottom edge.
            if ((float) $word->getAttribute('yMax') - (float) $word->getAttribute('yMin') < 2) {
                continue;
            }
            $this->assertGreaterThanOrEqual(64, (float) $word->getAttribute('xMin'), $word->textContent);
            $this->assertLessThanOrEqual(548, (float) $word->getAttribute('xMax'), $word->textContent);
            $this->assertLessThanOrEqual(738, (float) $word->getAttribute('yMax'), $word->textContent);
        }
        // Compare extracted text blocks, not raster pixels or font antialiasing.
        $blocks = iterator_to_array($doc->getElementsByTagName('block'));
        foreach ($blocks as $i => $a) {
            foreach (array_slice($blocks, $i + 1) as $b) {
                $overlapX = min((float) $a->getAttribute('xMax'), (float) $b->getAttribute('xMax'))
                    - max((float) $a->getAttribute('xMin'), (float) $b->getAttribute('xMin'));
                $overlapY = min((float) $a->getAttribute('yMax'), (float) $b->getAttribute('yMax'))
                    - max((float) $a->getAttribute('yMin'), (float) $b->getAttribute('yMin'));
                $this->assertFalse($overlapX > 1 && $overlapY > 1, 'Overlapping text blocks');
            }
        }
    }

    public function testInstitutionalCoverInAllLocales(): void
    {
        foreach (['pt_BR', 'es', 'en'] as $locale) {
            copy(self::TESTS_DIRECTORY . self::ASSETS_DIRECTORY . 'testTwoPages.pdf', $this->pathOfTestPdf2);
            $submission = $this->getSubmissionForTests();
            $submission->setData('version', '12');
            $submission->setData('isTranslation', true);
            $page = new TitlePage($submission, $this->checklist, $locale);
            $pdf = new Pdf($this->pathOfTestPdf2);
            $page->insertTitlePageFirstTime($pdf);
            $this->assertSame(4, $pdf->getNumberOfPages());
            $text = $this->text($pdf->getPath());
            foreach ([$this->title[$locale], $this->subtitle[$locale], $this->doi, $this->submissionDate, $this->publicationDate] as $value) {
                $this->assertStringContainsString($value, $text);
            }
            $previous = -1;
            foreach ($this->authorNames[$locale] as $name) {
                $this->assertStringContainsString($name, $text);
                $position = strpos($text, $name);
                $this->assertGreaterThan($previous, $position);
                $previous = $position;
            }
            foreach (['preprint', 'doi', 'notPeerReviewed', 'disclaimer', 'submitted', 'posted'] as $key) {
                $this->assertStringContainsString($this->coverText($key, $locale), $text);
            }
            $this->assertStringContainsString($this->coverText('version', $locale, ['version' => '12']), $text);
            $this->assertStringContainsString($this->coverText('postedValue', $locale, ['date' => $this->publicationDate, 'version' => '12']), $text);
            foreach ([$this->doiJournal, $this->versionJustification, $this->citation, 'Carl Sagan', 'Marie Curie'] as $removed) {
                $this->assertStringNotContainsString($removed, $text);
            }
            $this->assertStringNotContainsString(__('plugins.generic.titlePageForPreprint.publicationStatus', [], $locale), $text);
            foreach ($this->checklist[$locale] as $item) {
                $this->assertStringContainsString($item, $this->text($pdf->getPath(), 4));
            }
            $header = __('plugins.generic.titlePageForPreprint.headerText', ['doiPreprint' => 'https://doi.org/' . $this->doi], $locale);
            $this->assertStringContainsString($header, $this->text($pdf->getPath(), 2));
            $this->assertStringNotContainsString('SciELO Preprints', $this->text($pdf->getPath(), 2));
            $this->assertPageBounds($pdf->getPath());
            $annotations = shell_exec('pdftohtml -xml -i -stdout -f 1 -l 1 ' . escapeshellarg($pdf->getPath()));
            $this->assertStringContainsString('https://doi.org/' . $this->doi, $annotations);
        }
    }

    public function testEmptySubtitleDoesNotReserveAnEmptyLine(): void
    {
        $submission = $this->getSubmissionForTests();
        $positions = [];
        foreach ([true, false] as $present) {
            copy(self::TESTS_DIRECTORY . self::ASSETS_DIRECTORY . 'testOnePage.pdf', $this->pathOfTestPdf);
            if (!$present) {
                $submission->unsetData('subtitle');
            }
            (new TitlePage($submission, $this->checklist, $this->locale))->insertTitlePageFirstTime(new Pdf($this->pathOfTestPdf));
            $this->assertSame(3, (new Pdf($this->pathOfTestPdf))->getNumberOfPages());
            $xml = shell_exec('pdftotext -f 1 -l 1 -bbox ' . escapeshellarg($this->pathOfTestPdf) . ' -');
            $doc = new DOMDocument();
            $doc->loadXML($xml);
            foreach ($doc->getElementsByTagName('word') as $word) {
                if ($word->textContent === 'Cleide') {
                    $positions[] = (float) $word->getAttribute('yMin');
                }
            }
            if (!$present) {
                $this->assertStringNotContainsString($this->subtitle[$this->locale], $this->text($this->pathOfTestPdf));
            }
        }
        $this->assertCount(2, $positions);
        $this->assertLessThan($positions[0] - 10, $positions[1]);
    }

    public function testBothOfficialLogosWithoutSoftMasks(): void
    {
        $page = new TitlePage($this->getSubmissionForTests(), $this->checklist, $this->locale);
        $page->insertTitlePageFirstTime(new Pdf($this->pathOfTestPdf));
        $images = shell_exec('pdfimages -f 1 -l 1 -list ' . escapeshellarg($this->pathOfTestPdf));
        $this->assertSame(2, preg_match_all('/^\s+1\s+\d+\s+image\s/m', $images));
        $this->assertStringNotContainsString('smask', $images);
        $this->assertMatchesRegularExpression('/486\s+145\s+rgb/', $images);
        $this->assertMatchesRegularExpression('/1945\s+809\s+rgb/', $images);
        shell_exec('pdfimages -f 1 -l 1 -png ' . escapeshellarg($this->pathOfTestPdf) . ' ' . escapeshellarg($this->testDirectory . '/logo'));
        foreach (['lilacs-logo.jpg', 'lilacs-preprint-logo.png'] as $i => $name) {
            $expected = new Imagick(dirname(__DIR__) . '/resources/' . $name);
            $actual = new Imagick($this->testDirectory . '/logo-00' . $i . '.png');
            // JPEG decoding may differ slightly across libraries; compare tolerantly.
            $difference = $expected->compareImages($actual, Imagick::METRIC_MEANSQUAREERROR);
            $this->assertLessThan(0.001, $difference[1]);
        }
    }

    public function testAbsentSubtitleAndLongMetadataRemainOnOneCover(): void
    {
        foreach ([false, true] as $withSubtitle) {
            copy(self::TESTS_DIRECTORY . self::ASSETS_DIRECTORY . 'testOnePage.pdf', $this->pathOfTestPdf);
            $submission = $this->getSubmissionForTests();
            $title = 'Saúde pública e acesso equitativo aos serviços: análise longitudinal das desigualdades regionais e das políticas de atenção integral nas comunidades latino-americanas';
            $subtitle = 'Resultados de um estudo multicêntrico sobre vigilância, prevenção e promoção da saúde, considerando diferenças sociais, culturais e territoriais ao longo de uma década';
            $names = [];
            for ($i = 1; $i <= 10; $i++) {
                $names[] = 'Autora ' . $i . ' — João Gonçalves da Silva';
            }
            $submission->setData('title', [$this->locale => $title]);
            $submission->setData('subtitle', [$this->locale => $withSubtitle ? $subtitle : '']);
            $submission->setData('authorNames', [$this->locale => $names]);
            $submission->setData('version', '2');
            $page = new TitlePage($submission, $this->checklist, $this->locale);
            $pdf = new Pdf($this->pathOfTestPdf);
            $page->insertTitlePageFirstTime($pdf);
            $this->assertSame(3, $pdf->getNumberOfPages());
            $text = $this->text($pdf->getPath());
            $this->assertStringContainsString($title, $text);
            if ($withSubtitle) {
                $this->assertStringContainsString($subtitle, $text);
            } else {
                $this->assertStringNotContainsString($subtitle, $text);
            }
            foreach ($names as $name) {
                $this->assertStringContainsString($name, $text);
            }
            $this->assertStringContainsString($this->coverText('disclaimer', $this->locale), $text);
            $this->assertPageBounds($pdf->getPath());
        }
    }

    public function testUpdateReplacesOnlyCoverAndPreservesManuscriptAndChecklist(): void
    {
        $submission = $this->getSubmissionForTests();
        $page = new TitlePage($submission, $this->checklist, $this->locale);
        $stamped = $this->testDirectory . '/stamped.pdf';
        copy($this->pathOfTestPdf2, $stamped);
        $page->addDocumentHeader($stamped);
        $pdf = new Pdf($this->pathOfTestPdf2);
        $page->insertTitlePageFirstTime($pdf);
        $this->assertSame(4, $pdf->getNumberOfPages());
        $checklist = $this->text($pdf->getPath(), 4);
        $submission->setData('title', [$this->locale => 'Título atualizado']);
        $submission->setData('version', '2');
        $page->updateTitlePage($pdf);
        $page->updateTitlePage($pdf);
        $this->assertSame(4, $pdf->getNumberOfPages());
        $this->assertStringContainsString('Título atualizado', $this->text($pdf->getPath()));
        $this->assertStringNotContainsString($this->title[$this->locale], $this->text($pdf->getPath()));
        $this->assertSame(1, substr_count($this->text($pdf->getPath()), $this->coverText('notPeerReviewed', $this->locale)));
        $this->assertSame($checklist, $this->text($pdf->getPath(), 4));
        for ($i = 1; $i <= 2; $i++) {
            $this->assertSame($this->text($stamped, $i), $this->text($pdf->getPath(), $i + 1));
            $images = [];
            foreach ([[$stamped, $i], [$pdf->getPath(), $i + 1]] as $j => [$path, $number]) {
                $prefix = $this->testDirectory . '/page' . $j;
                shell_exec('pdftoppm -f ' . $number . ' -l ' . $number . ' -singlefile -scale-to 1000 -png ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix));
                $images[] = new Imagick($prefix . '.png');
            }
            $difference = $images[0]->compareImages($images[1], Imagick::METRIC_MEANSQUAREERROR);
            $this->assertEquals(0, $difference[1]);
        }
    }

    public function testImpossibleMetadataFailsWithoutChangingOriginal(): void
    {
        $submission = $this->getSubmissionForTests();
        $submission->setData('title', [$this->locale => str_repeat('Metadados muito extensos ', 1000)]);
        $page = new TitlePage($submission, $this->checklist, $this->locale);
        $requirements = $this->getMockBuilder(TitlePageRequirements::class)->onlyMethods(['showMissingRequirementNotification'])->getMock();
        $requirements->expects($this->once())->method('showMissingRequirementNotification')->with('plugins.generic.titlePageForPreprint.cover.tooLong');
        $property = new ReflectionProperty(TitlePage::class, 'titlePageRequirements');
        $property->setAccessible(true);
        $property->setValue($page, $requirements);
        $before = hash_file('sha256', $this->pathOfTestPdf);
        try {
            $page->insertTitlePageFirstTime(new Pdf($this->pathOfTestPdf));
            $this->fail('Expected metadata overflow');
        } catch (LengthException $e) {
            $this->assertSame($before, hash_file('sha256', $this->pathOfTestPdf));
        }
    }
}
