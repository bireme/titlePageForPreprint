<?php

namespace APP\plugins\generic\titlePageForPreprint\classes;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\core\Application;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionPress;
use APP\plugins\generic\titlePageForPreprint\classes\SubmissionModel;
use APP\plugins\generic\titlePageForPreprint\classes\GalleyAdapterFactory;

class SubmissionPressFactory
{
    public function createSubmissionPress($submission, $publication, $context): SubmissionPress
    {
        $checklist = $this->getContextChecklist($context);
        $dataForPress = $this->getDataForPress($submission, $publication);
        $galleys = $publication->getData('galleys');
        $submissionGalleys = [];

        foreach ($galleys as $galley) {
            $submissionFileRepo = Repo::submissionFile();
            $galleyAdapterFactory = new GalleyAdapterFactory($submissionFileRepo);
            $submissionGalleys[] = $galleyAdapterFactory->createGalleyAdapter($submission, $galley);
        }

        $dataForPress['galleys'] = $submissionGalleys;
        $submissionModel = new SubmissionModel();
        $submissionModel->setAllData($dataForPress);

        return new SubmissionPress($submissionModel, $checklist);
    }

    private function getContextChecklist($context): array
    {
        $checklist = $context->getData('submissionChecklist');

        foreach ($checklist as $locale => $checklistText) {
            preg_match_all('/<li>(.*?)<\/li>/', $checklistText, $matches);

            $checklist[$locale] = $matches[1];
        }

        return $checklist;
    }


    private function getAuthorNames($publication): array
    {
        $names = [];
        // Resolve names for every galley locale with PKP's own locale fallback.
        $locales = array_keys($publication->getTitles());
        foreach ($publication->getData('galleys') ?? [] as $galley) {
            $locales[] = $galley->getData('locale');
        }
        $locales = array_unique($locales);
        foreach ($locales as $locale) {
            foreach ($publication->getData('authors') ?? [] as $author) {
                $names[$locale][] = $author->getFullName(true, false, $locale);
            }
        }
        return $names;
    }

    private function getDataForPress($submission, $publication)
    {
        $data = [];

        $data['title'] = $publication->getTitles();
        $data['subtitle'] = $publication->getSubTitles();
        $data['doi'] = $publication->getStoredPubId('doi');
        $data['doiJournal'] = $publication->getData('vorDoi');
        $data['authorNames'] = $this->getAuthorNames($publication);
        $data['version'] = $publication->getData('version');
        $data['versionJustification'] = $publication->getData('versionJustification');

        $dateSubmitted = strtotime($submission->getData('dateSubmitted'));
        $data['submissionDate'] = date('Y-m-d', $dateSubmitted);
        $datePublished = strtotime($publication->getData('datePublished'));
        $data['publicationDate'] = date('Y-m-d', $datePublished);

        $data['isTranslation'] = !is_null($publication->getData('originalDocumentDoi'));
        $data['citation'] = ($data['isTranslation'] ? $this->getSubmissionCitation($submission) : '');

        $titlePageDao = new TitlePageDAO();
        $data['endorsers'] = $titlePageDao->getEndorsersBySubmission($submission);

        $status = $publication->getData('relationStatus');
        $relation = [Publication::PUBLICATION_RELATION_NONE => 'publication.relation.none', Publication::PUBLICATION_RELATION_PUBLISHED => 'publication.relation.published'];
        $data['status'] = ($status) ? ($relation[$status]) : ("");

        return $data;
    }

    private function getSubmissionCitation($submission)
    {
        $request = Application::get()->getRequest();
        $cslPlugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');

        $citation = $cslPlugin->getCitation($request, $submission, 'apa');

        return $citation;
    }
}
