<?php

namespace Appvise\AppStoreNotifications;

use Illuminate\Http\Request;
use Appvise\AppStoreNotifications\Model\NotificationType;
use Appvise\AppStoreNotifications\Model\AppleNotification;
use Appvise\AppStoreNotifications\Exceptions\WebhookFailed;
use Appvise\AppStoreNotifications\Model\NotificationPayload;
use Illuminate\Support\Facades\Log;

class WebhooksController
{
    public function __invoke(Request $request)
    {
        $jobConfigKey = NotificationType::{$request->input('notification_type')}();
        $this->determineValidRequest($request->input('password'));

        $notificationId = AppleNotification::storeNotification($jobConfigKey, $request->input());

        // FIXME: rjgonzale, why is the latest receipt null? this is for debugging
        if (!$request->has('latest_receipt_info')) {
            Log::error("Notification with id " . $notificationId . " does not have latest_receipt_info");
        }

        $payload = NotificationPayload::createFromRequest($request);

        $jobClass = config("appstore-server-notifications.jobs.{$jobConfigKey}", null);

        if (is_null($jobClass)) {
            throw WebhookFailed::jobClassDoesNotExist($jobConfigKey);
        }

        $job = new $jobClass($payload);
        dispatch($job);

        return response()->json();
    }

    private function determineValidRequest(string $password): bool
    {
        if ($password !== config('appstore-server-notifications.shared_secret')) {
            throw WebhookFailed::nonValidRequest();
        }

        return true;
    }
}
