<?php

use APP\plugins\generic\titlePageForPreprint\tests\PdfHandlingTest;
use APP\plugins\generic\titlePageForPreprint\classes\GalleyAdapter;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionFileUpdater;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionPress;
use APP\plugins\generic\titlePageForPreprint\classes\Pdf;

class SubmissionPressTest extends PdfHandlingTest
{
    private function buildPress($submission, bool $hasTitlePage = false): SubmissionPress
    {
        $press = $this->getMockBuilder(SubmissionPress::class)
            ->setConstructorArgs([$submission, $this->checklist])
            ->onlyMethods(['galleyHasTitlePage'])
            ->getMock();
        $press->method('galleyHasTitlePage')->willReturn($hasTitlePage);
        return $press;
    }

    private function buildMockGalleyAdapter($args): GalleyAdapter
    {
        $mockGalley = $this->getMockBuilder(GalleyAdapter::class)
            ->setConstructorArgs($args)
            ->onlyMethods(['getFullFilePath'])
            ->getMock();

        $mockGalley->method('getFullFilePath')->willReturn($args[0]);

        return $mockGalley;
    }

    private function buildMockSubmissionFileUpdater(): SubmissionFileUpdater
    {
        $mockUpdater = $this->getMockBuilder(SubmissionFileUpdater::class)
            ->onlyMethods(['updateRevisions'])
            ->getMock();

        return $mockUpdater;
    }

    public function testInsertsCorrectlySingleGalley(): void
    {
        $galleyPath = $this->pathOfTestPdf;
        $galley = $this->buildMockGalleyAdapter([$galleyPath, $this->locale, 1, 2]);
        $submission = $this->getSubmissionForTests();
        $submission->setData('galleys', [$galley]);
        $press = $this->buildPress($submission);

        $press->insertTitlePage($this->buildMockSubmissionFileUpdater());

        $pdfOfGalley = new Pdf($galleyPath);
        $this->assertEquals(3, $pdfOfGalley->getNumberOfPages());
    }

    public function testInsertsCorrectlyMultipleGalleys(): void
    {
        $fistGalleyPath = $this->pathOfTestPdf;
        $secondGalleyPath = $this->pathOfTestPdf2;
        $firstGalley = $this->buildMockGalleyAdapter(array($fistGalleyPath, $this->locale, 2, 2));
        $secondGalley = $this->buildMockGalleyAdapter(array($secondGalleyPath, "en", 3, 2));
        $submission = $this->getSubmissionForTests();
        $submission->setData('galleys', [$firstGalley, $secondGalley]);

        $press = $this->buildPress($submission);
        $press->insertTitlePage($this->buildMockSubmissionFileUpdater());

        $pdfOfFirstGalley = new Pdf($fistGalleyPath);
        $pdfOfSecondGalley = new Pdf($secondGalleyPath);

        $this->assertEquals(3, $pdfOfFirstGalley->getNumberOfPages());
        $this->assertEquals(4, $pdfOfSecondGalley->getNumberOfPages());
    }

    public function testUpdatesExistingGalleyWithoutDuplicatingCoverOrChecklist(): void
    {
        $galley = $this->buildMockGalleyAdapter([$this->pathOfTestPdf, $this->locale, 1, 2]);
        $submission = $this->getSubmissionForTests();
        $submission->setData('galleys', [$galley]);
        $this->buildPress($submission)->insertTitlePage($this->buildMockSubmissionFileUpdater());
        $submission->setData('version', '2');
        $updater = $this->buildMockSubmissionFileUpdater();
        $updater->expects($this->once())->method('updateRevisions')->with(1, 2, true);
        $this->buildPress($submission, true)->insertTitlePage($updater);
        $this->assertSame(3, (new Pdf($this->pathOfTestPdf))->getNumberOfPages());
    }

    public function testMustIgnoreNotPdfFiles(): void
    {
        $fistGalleyPath = $this->pathOfTestPdf;
        $secondGalleyPath = PdfHandlingTest::TESTS_DIRECTORY . PdfHandlingTest::ASSETS_DIRECTORY . "fileNotPdf.odt";
        $firstGalley = $this->buildMockGalleyAdapter(array($fistGalleyPath, $this->locale, 4, 2));
        $secondGalley = $this->buildMockGalleyAdapter(array($secondGalleyPath, $this->locale, 5, 2));
        $submission = $this->getSubmissionForTests();
        $submission->setData('galleys', [$firstGalley, $secondGalley]);

        $hashOfNotPdfGalley = md5_file($secondGalleyPath);
        $press = $this->buildPress($submission);
        $press->insertTitlePage($this->buildMockSubmissionFileUpdater());

        $pdfOfFirstGalley = new Pdf($fistGalleyPath);

        $this->assertEquals(3, $pdfOfFirstGalley->getNumberOfPages());
        $this->assertEquals($hashOfNotPdfGalley, md5_file($secondGalleyPath));
    }
}
