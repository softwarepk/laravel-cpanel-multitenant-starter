<?php

namespace App\Http\Controllers;

use App\Services\InstallationDiscovery;
use App\Services\InstallationState;
use App\Services\QueueCronInstaller;
use App\Services\WebInstaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationDiscovery $discovery,
        private readonly InstallationState $state,
        private readonly WebInstaller $installer,
        private readonly QueueCronInstaller $queueCron,
    ) {}

    public function create(Request $request): Response
    {
        if (! $this->state->requiresInstallation()) {
            return new RedirectResponse('/central/login', 302);
        }

        return $this->render($request);
    }

    public function checkCpanel(Request $request): Response
    {
        if (! $this->state->requiresInstallation()) {
            return response()->json(['message' => 'This deployment is already installed.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'central_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'platform_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'document_root' => ['required', 'string', 'max:500'],
            'cpanel_host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.-]+$/'],
            'cpanel_port' => ['required', 'integer', 'in:2083'],
            'cpanel_user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'cpanel_token' => ['required', 'string', 'min:20', 'max:1024'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $host = strtolower($request->getHost());
            if (strtolower((string) $request->input('central_domain')) !== $host) {
                $validator->errors()->add('central_domain', 'The central domain must match the hostname currently serving this installer.');
            }

            if ($this->normalizedPath((string) $request->input('document_root')) !== $this->normalizedPath(public_path())) {
                $validator->errors()->add('document_root', 'The document root must be this deployment\'s Laravel public directory.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Review the cPanel connection fields and try again.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            $result = $this->installer->verifyCpanelConfiguration($validator->validated(), strtolower($request->getHost()));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'cPanel connection verified. The account manages this installer hostname and tenant platform root.',
            'database_host' => $result['database_host'],
        ]);
    }

    public function store(Request $request): Response
    {
        if (! $this->state->requiresInstallation()) {
            return new RedirectResponse('/central/login', 302);
        }

        $validator = Validator::make($request->all(), [
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url:https', 'max:255'],
            'central_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'platform_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
            'document_root' => ['required', 'string', 'max:500'],
            'custom_domain_dns_target' => ['required', 'string', 'max:253'],
            'cpanel_host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.-]+$/'],
            'cpanel_port' => ['required', 'integer', 'in:2083'],
            'cpanel_user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'cpanel_token' => ['required', 'string', 'min:20', 'max:1024'],
            'db_host' => ['required', 'string', 'max:253'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'central_db_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'central_db_user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'central_db_password' => ['required', 'string', 'min:12', 'max:255'],
            'tenant_db_host' => ['required', 'string', 'max:253'],
            'tenant_db_port' => ['required', 'integer', 'between:1,65535'],
            'tenant_db_user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'different:central_db_user'],
            'tenant_db_password' => ['required', 'string', 'min:12', 'max:255'],
            'tenant_db_prefix' => ['required', 'string', 'max:46', 'regex:/^[A-Za-z0-9_]+$/'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
            'registration' => ['nullable', 'boolean'],
            'verification' => ['nullable', 'boolean'],
            'confirm_install' => ['accepted'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $host = strtolower($request->getHost());
            if (strtolower((string) $request->input('central_domain')) !== $host) {
                $validator->errors()->add('central_domain', 'The central domain must match the hostname currently serving this installer.');
            }

            $appHost = strtolower((string) parse_url((string) $request->input('app_url'), PHP_URL_HOST));
            if ($appHost !== $host) {
                $validator->errors()->add('app_url', 'The application URL must use the hostname currently serving this installer.');
            }

            if ($this->normalizedPath((string) $request->input('document_root')) !== $this->normalizedPath(public_path())) {
                $validator->errors()->add('document_root', 'The document root must be this deployment\'s Laravel public directory.');
            }
        });

        if ($validator->fails()) {
            return $this->render($request, $validator->errors()->toArray(), null, 422);
        }

        $data = $validator->validated();
        $data['registration'] = $request->boolean('registration');
        $data['verification'] = $request->boolean('verification');

        try {
            $result = $this->installer->install($data, strtolower($request->getHost()));
        } catch (Throwable $e) {
            report($e);

            return $this->render(
                $request,
                [],
                $e->getMessage(),
                500,
            );
        }

        return response()->view('install.complete', [
            'centralDomain' => $result['central_domain'],
            'centralDatabase' => $result['central_database'],
            'tenantDatabaseUser' => $result['tenant_database_user'],
            'queue' => $result['queue'],
        ]);
    }

    /** @param array<string, list<string>> $errors */
    private function render(Request $request, array $errors = [], ?string $globalError = null, int $status = 200): Response
    {
        $discovery = $this->discovery->discover($request);
        $values = array_replace($discovery['defaults'], $this->submittedNonSecretValues($request));
        $checksReady = count(array_filter($discovery['checks'], static fn (array $check): bool => ! $check['ok'])) === 0;

        return response()->view('install.index', [
            'values' => $values,
            'checks' => $discovery['checks'],
            'checksReady' => $checksReady,
            'queuePlan' => $this->queueCron->plan(),
            'errors' => $errors,
            'globalError' => $globalError,
            'pending' => $this->state->isPending(),
            'initialStep' => $this->initialStep($request, $errors, $globalError),
        ], $status);
    }

    /** @param array<string, list<string>> $errors */
    private function initialStep(Request $request, array $errors, ?string $globalError): int
    {
        $step = $globalError !== null ? 7 : 1;

        foreach (array_keys($errors) as $key) {
            if (in_array($key, ['app_name', 'app_url', 'central_domain', 'platform_domain', 'document_root', 'custom_domain_dns_target'], true)) {
                $step = 2;
                break;
            }
            if (str_starts_with($key, 'cpanel_')) {
                $step = 3;
                break;
            }
            if (str_starts_with($key, 'db_') || str_starts_with($key, 'central_db_') || str_starts_with($key, 'tenant_db_')) {
                $step = 4;
                break;
            }
            if (in_array($key, ['admin_name', 'admin_email', 'admin_password', 'registration', 'verification'], true)) {
                $step = 6;
                break;
            }
            if ($key === 'confirm_install') {
                $step = 7;
                break;
            }
        }

        // Secrets are intentionally never redisplayed after a POST. If the
        // submission got beyond application validation, resume at cPanel so the
        // operator can re-enter the token and then the DB/admin passwords in order.
        if ($request->isMethod('post') && $step > 3) {
            return 3;
        }

        return $step;
    }

    /** @return array<string, string|int|bool> */
    private function submittedNonSecretValues(Request $request): array
    {
        $keys = [
            'app_name', 'app_url', 'central_domain', 'platform_domain', 'document_root', 'custom_domain_dns_target',
            'cpanel_host', 'cpanel_port', 'cpanel_user', 'db_host', 'db_port', 'central_db_name', 'central_db_user',
            'tenant_db_host', 'tenant_db_port', 'tenant_db_user', 'tenant_db_prefix', 'admin_name', 'admin_email',
        ];

        $values = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                if (is_string($value) || is_int($value) || is_bool($value)) {
                    $values[$key] = $value;
                }
            }
        }
        if ($request->isMethod('post')) {
            $values['registration'] = $request->boolean('registration');
            $values['verification'] = $request->boolean('verification');
        }

        return $values;
    }

    private function normalizedPath(string $path): string
    {
        $path = rtrim(trim($path), '/\\');
        $real = $path !== '' ? realpath($path) : false;

        return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
    }
}
