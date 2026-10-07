<?php

namespace APP\plugins\generic\titlePageForPreprint\classes;

use PKP\core\DataObject;

class SubmissionModel extends DataObject
{
    public function getTitle(string $locale): string
    {
        $localizedTitle = $this->getData('title', $locale);

        if (!is_null($localizedTitle)) {
            return $localizedTitle;
        }

        return '';
    }

    public function getSubtitle(string $locale): string
    {
        return $this->getData('subtitle', $locale) ?? '';
    }

    public function getAuthorNames(string $locale): array
    {
        $names = $this->getData('authorNames', $locale);
        if ($names !== null) {
            return $names;
        }
        // Legacy callers may still supply a display string; never split names on punctuation.
        $legacy = $this->getData('authors');
        return empty($legacy) ? [] : [$legacy];
    }

    public function getStatus(): string
    {
        return $this->getData('status');
    }

    public function getDOI(): string
    {
        $doi = $this->getData('doi');
        return empty($doi) ? ("Not informed") : $doi;
    }

    public function getJournalDOI(): string
    {
        $doiJournal = $this->getData('doiJournal');
        return empty($doiJournal) ? ("Not informed") : $doiJournal;
    }

    public function getAuthors(): string
    {
        $legacy = $this->getData('authors');
        if ($legacy !== null) {
            return $legacy;
        }
        $localizedNames = $this->getData('authorNames') ?? [];
        return implode('; ', reset($localizedNames) ?: []);
    }

    public function getGalleys(): array
    {
        $galleys = $this->getData('galleys');
        return is_null($galleys) ? [] : $galleys;
    }

    public function getSubmissionDate(): string
    {
        return $this->getData('submissionDate');
    }

    public function getPublicationDate(): string
    {
        return $this->getData('publicationDate');
    }

    public function getVersion(): string
    {
        return $this->getData('version');
    }

    public function getEndorsers(): ?array
    {
        return $this->getData('endorsers');
    }

    public function getVersionJustification(): ?string
    {
        return $this->getData('versionJustification');
    }

    public function setIsTranslation(bool $isTranslation)
    {
        $this->setData('isTranslation', $isTranslation);
    }

    public function getIsTranslation(): bool
    {
        return $this->getData('isTranslation') ?? false;
    }

    public function getCitation(): ?string
    {
        return $this->getData('citation');
    }
}
