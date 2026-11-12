<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Analytics\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/analytics', name: 'admin_analytics_')]
#[IsGranted('analytics.view')]
final class AnalyticsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        [$from, $to] = $this->range($request);

        return $this->render('admin/analytics/index.html.twig', [
            'report' => $analytics->report($from, $to),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }

    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, AnalyticsService $analytics): Response
    {
        [$from, $to] = $this->range($request);
        $report = $analytics->report($from, $to);

        $handle = fopen('php://temp', 'wb');
        \assert(false !== $handle);
        fputcsv($handle, ['Fieldnotes analytics', $from->format('Y-m-d').' to '.$to->format('Y-m-d')], ',', '"', '');
        fputcsv($handle, ['Date', 'Views'], ',', '"', '');
        foreach ($report['daily']['labels'] as $index => $label) {
            fputcsv($handle, [$label, $report['daily']['values'][$index]], ',', '"', '');
        }
        fputcsv($handle, [], ',', '"', '');
        fputcsv($handle, ['Article', 'Views', 'Likes', 'Approved comments', 'Bookmarks', 'Engagement', 'Engagement rate %'], ',', '"', '');
        foreach ($report['articles'] as $article) {
            fputcsv($handle, [$article['title'], $article['views'], $article['likes'], $article['comments'], $article['bookmarks'], $article['engagement'], $article['engagement_rate']], ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="analytics-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv"',
        ]);
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    private function range(Request $request): array
    {
        $today = new \DateTimeImmutable('today');
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('from')) ?: $today->modify('-29 days');
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->query->getString('to')) ?: $today;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from < $to->modify('-365 days')) {
            $from = $to->modify('-365 days');
        }

        return [$from, $to];
    }
}
