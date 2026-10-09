<?php

namespace APP\plugins\generic\titlePageForPreprint\tests;

use PKP\tests\PKPTestCase;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionModel;
use APP\plugins\generic\titlePageForPreprint\classes\Endorser;

class PdfHandlingTest extends PKPTestCase
{
    public const TESTS_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR;
    public const ASSETS_DIRECTORY = 'assets' . DIRECTORY_SEPARATOR;

    protected $status = "publication.relation.none";
    protected $doi = "10.1000/182";
    protected $doiJournal = "https://doi.org/10.1590/1413-81232020256.1.10792020";
    protected $checklist = [
        "en" => ["The submission has not been previously published.", "Where available, URLs for the references have been provided."],
        "es" => ["El envío no ha sido publicado previamente.", "Se han proporcionado las URL de las referencias."],
        "pt_BR" => ["A submissão não foi publicado anteriormente.", "As URLs das referências foram fornecidas."]
    ];
    protected $locale = "pt_BR";
    protected $title = [
        'pt_BR' => "Assim Falou Zaratustra-àáâã",
        'en' => 'Thus spoke Zarathustra',
        'es' => 'Así habló Zaratustra'
    ];
    protected $subtitle = ['pt_BR' => 'Uma análise sobre saúde pública', 'es' => 'Un análisis sobre salud pública', 'en' => 'An analysis of public health'];
    protected $authorNames = [
        'pt_BR' => ['Cleide Silva', 'João Carlos', 'María González'],
        'es' => ['Cleide Silva', 'João Carlos', 'María González'],
        'en' => ['Cleide Silva', 'João Carlos', 'María González'],
    ];
    protected $testDirectory;
    protected $pathOfTestPdf;
    protected $pathOfTestPdf2;
    protected $authors = "Cleide Silva; João Carlos";
    protected $submissionDate = "2020-06-30";
    protected $publicationDate = "2020-07-02";
    protected $endorsers = [
        ['name' => 'Carl Sagan', 'orcid' => 'https://orcid.org/0123-4567-89AB-CDEF'],
        ['name' => 'Marie Curie', 'orcid' => 'https://orcid.org/0123-4567-89AB-RDIO']
    ];
    protected $version = "1";
    protected $versionJustification = 'Nova versão criada para corrigir erros de ortografia';
    protected $isTranslation = false;
    protected $citation = 'Silva, C. & Carlos, J. (2024). Thus spoke Zarathustra. Public Knowledge Preprint Server';

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDirectory = sys_get_temp_dir() . '/lilacs-test-' . uniqid();
        mkdir($this->testDirectory);
        $this->pathOfTestPdf = $this->testDirectory . '/one.pdf';
        $this->pathOfTestPdf2 = $this->testDirectory . '/two.pdf';
        copy(self::TESTS_DIRECTORY . self::ASSETS_DIRECTORY . 'testOnePage.pdf', $this->pathOfTestPdf);
        copy(self::TESTS_DIRECTORY . self::ASSETS_DIRECTORY . 'testTwoPages.pdf', $this->pathOfTestPdf2);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->testDirectory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->testDirectory);
        parent::tearDown();
    }

    protected function getSubmissionForTests(): SubmissionModel
    {
        $submission = new SubmissionModel();
        $submission->setAllData([
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'authorNames' => $this->authorNames,
            'status' => $this->status,
            'doi' => $this->doi,
            'doiJournal' => $this->doiJournal,
            'authors' => $this->authors,
            'submissionDate' => $this->submissionDate,
            'publicationDate' => $this->publicationDate,
            'endorsers' => $this->createTestEndorsers(),
            'version' => $this->version,
            'versionJustification' => $this->versionJustification,
            'isTranslation' => $this->isTranslation,
            'citation' => $this->citation,
        ]);

        return $submission;
    }

    private function createTestEndorsers(): array
    {
        $endorsers = [];

        foreach ($this->endorsers as $endorserData) {
            $endorsers[] = new Endorser(
                $endorserData['name'],
                $endorserData['orcid']
            );
        }

        return $endorsers;
    }

    public function testDummy(): void
    {
        $this->assertTrue(true);
    }
}
