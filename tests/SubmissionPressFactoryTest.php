<?php

use PKP\tests\PKPTestCase;
use PKP\core\DataObject;
use PKP\author\Author;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionPressFactory;

class SubmissionPressFactoryTest extends PKPTestCase
{
    public function testAuthorOrderAndGalleyLocaleFallback(): void
    {
        $first = new Author();
        $first->setData('submissionLocale', 'pt_BR');
        $first->setData('givenName', ['pt_BR' => 'João', 'en' => 'John']);
        $first->setData('familyName', ['pt_BR' => 'Silva', 'en' => 'Silva']);
        $first->setData('preferredPublicName', ['es' => 'Juan Silva']);
        $second = new Author();
        $second->setData('submissionLocale', 'pt_BR');
        $second->setData('givenName', ['pt_BR' => 'María']);
        $second->setData('familyName', ['pt_BR' => 'García']);
        $galley = new DataObject();
        $galley->setData('locale', 'es');
        // Only the Publication API consumed by this pure extraction is needed.
        $publication = new class extends DataObject {
            public function getTitles(string $format = 'text')
            {
                return ['pt_BR' => 'Título', 'en' => 'Title'];
            }
        };
        $publication->setData('galleys', [$galley]);
        $publication->setData('authors', [$first, $second]);
        $method = new ReflectionMethod(SubmissionPressFactory::class, 'getAuthorNames');
        $method->setAccessible(true);
        $names = $method->invoke(new SubmissionPressFactory(), $publication);
        $this->assertSame(['João Silva', 'María García'], $names['pt_BR']);
        $this->assertSame(['John Silva', 'María García'], $names['en']);
        $this->assertSame(['Juan Silva', 'María García'], $names['es']);
    }
}
