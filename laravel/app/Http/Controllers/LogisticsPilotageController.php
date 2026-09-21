<?php

namespace App\Http\Controllers;

use App\Models\LogisticsPilotageNotification;
use App\Services\LogisticsPilotageDataService;
use App\Services\LogisticsVehicleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogisticsPilotageController extends Controller
{
    public function reports(Request $request, LogisticsPilotageDataService $pilotage)
    {
        [$from, $to] = $pilotage->resolvePeriod($request->query('from'), $request->query('to'));
        $report = $pilotage->report($from, $to, $request->query('territory'));

        if ($request->boolean('export')) {
            return $this->exportReport($report);
        }

        return view('logistics.pilotage.reports', $report);
    }

    public function notifications(Request $request, LogisticsPilotageDataService $pilotage)
    {
        $pilotage->syncRealNotifications(true);

        if ($request->filled('open')) {
            $notification = LogisticsPilotageNotification::query()
                ->where('status', 'active_real')
                ->find((int) $request->query('open'));

            if ($notification) {
                $notification->update(['is_read' => true]);
                $url = (string) data_get($notification->meta, 'url', '');
                if ($url !== '' && str_starts_with($url, '/')) {
                    return redirect()->to($url);
                }
            }

            return redirect()->route('logistics.control.notifications');
        }

        $query = LogisticsPilotageNotification::query()
            ->where('status', 'active_real')
            ->latest('occurred_at');

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }
        if ($request->filled('type') && $request->query('type') !== 'all') {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('priority') && $request->query('priority') !== 'all') {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->filled('read') && $request->query('read') !== 'all') {
            $query->where('is_read', $request->query('read') === 'read');
        }

        $period = (string) $request->query('period', 'today');
        if ($period === 'today') {
            $query->whereDate('occurred_at', today());
        } elseif ($period === '7d') {
            $query->where('occurred_at', '>=', now()->subDays(7));
        } elseif ($period === '30d') {
            $query->where('occurred_at', '>=', now()->subDays(30));
        }

        $notifications = $query->paginate(10)->withQueryString();
        $counts = $pilotage->notificationCounts();
        $settings = $pilotage->ensureSettings()->groupBy('group_key');
        $settingValues = $pilotage->settingValues();

        return view('logistics.pilotage.notifications', compact('notifications', 'counts', 'settings', 'settingValues'));
    }

    public function markAllRead(): RedirectResponse
    {
        if (Schema::hasTable('logistics_pilotage_notifications')) {
            LogisticsPilotageNotification::query()
                ->where('status', 'active_real')
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return back()->with('success', 'Toutes les notifications opérationnelles ont été marquées comme lues.');
    }

    public function settings(LogisticsPilotageDataService $pilotage, LogisticsVehicleResolver $vehicleResolver)
    {
        $allSettings = $pilotage->ensureSettings();
        $settings = $allSettings->groupBy('group_key');
        $values = $allSettings->pluck('value', 'setting_key');
        $vehicleRules = collect($vehicleResolver->catalog());

        return view('logistics.pilotage.settings', compact('settings', 'values', 'vehicleRules'));
    }

    public function saveSettings(Request $request, LogisticsPilotageDataService $pilotage): RedirectResponse
    {
        $pilotage->saveSettings((array) $request->input('settings', []));

        return back()->with('success', 'Paramètres logistiques enregistrés.');
    }

    protected function exportReport(array $report): StreamedResponse
    {
        $file = 'ovanie-rapport-logistique-' . $report['from']->format('Ymd') . '-' . $report['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['OVANIE Logistics - Rapport & performances'], ';');
            fputcsv($out, ['Période', $report['from']->format('d/m/Y') . ' - ' . $report['to']->format('d/m/Y')], ';');
            fputcsv($out, ['Territoire', $report['territory']?->name ?: 'Tous les territoires'], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Indicateur', 'Valeur', 'Évolution'], ';');
            foreach ($report['kpis'] as $kpi) {
                fputcsv($out, [$kpi['label'], $kpi['value'], $kpi['change_label']], ';');
            }
            fputcsv($out, [], ';');
            fputcsv($out, ['Top livreurs', 'Livraisons', 'Ponctualité', 'Incidents', 'Note'], ';');
            foreach ($report['driverPerformance'] as $row) {
                fputcsv($out, [$row['name'], $row['deliveries'], $row['punctuality'] === null ? '' : $row['punctuality'] . '%', $row['incidents'], $row['rating']], ';');
            }
            fputcsv($out, [], ';');
            fputcsv($out, ['Zones', 'Livraisons', 'Ponctualité', 'Incidents'], ';');
            foreach ($report['topZones'] as $row) {
                fputcsv($out, [$row['zone'], $row['deliveries'], $row['punctuality'] === null ? '' : $row['punctuality'] . '%', $row['incidents']], ';');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
