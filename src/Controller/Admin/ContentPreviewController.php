<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Mock\AdminPreview;
use App\Mock\MockDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Shared presentation context only; no persistence, queries or business rules. */
abstract class ContentPreviewController extends AbstractController
{
    public function __construct(private readonly AdminPreview $preview, private readonly MockDataProvider $data)
    {
    }

    protected function screen(Request $request, string $module, string $template, ?int $id = null): Response
    {
        $this->preview->requireEnabled();
        $record = $this->data->contentRecord($module, $id) ?? throw $this->createNotFoundException();

        return $this->render($template, [
            'module' => $module, 'record' => $record, 'records' => $this->data->contentRecords($module),
            'currentUser' => $this->data->users()[0], 'media' => $this->data->media(),
            'categories' => $this->data->categories(), 'pages' => $this->data->pages(),
            'state' => $request->query->getString('state'),
        ]);
    }
}
