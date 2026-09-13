<?php

namespace App\Filament\Resources\Origins\Pages;

use App\Filament\Resources\Origins\OriginResource;
use App\Models\Origin;
use App\Services\FastSms\FastSmsClient;
use App\Services\ReservaYa\ReservaYaClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditOrigin extends EditRecord
{
    protected static string $resource = OriginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->rotateSecretAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Hits both APIs with the stored credentials so a wrong token is caught
     * here instead of in a silent job failure hours later.
     */
    private function testConnectionAction(): Action
    {
        return Action::make('probarConexion')
            ->label('Probar conexión')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->action(function (Origin $record): void {
                $results = [
                    'ReservaYa' => $this->probe(
                        fn () => blank($record->reservaya_token)
                            ? null
                            : ReservaYaClient::forOrigin($record)->ping(),
                    ),
                    'FastSMS' => $this->probe(
                        fn () => $record->isFullyConfigured()
                            ? FastSmsClient::forOrigin($record)->ping()
                            : null,
                    ),
                ];

                $failed = array_filter($results, fn (string $result) => $result !== 'ok');

                $body = collect($results)
                    ->map(fn (string $result, string $service) => "{$service}: {$result}")
                    ->implode("\n");

                $notification = Notification::make()
                    ->title($failed === [] ? 'Ambas APIs responden' : 'Hay conexiones con problemas')
                    ->body($body);

                ($failed === [] ? $notification->success() : $notification->warning()->persistent())->send();
            });
    }

    private function rotateSecretAction(): Action
    {
        return Action::make('regenerarSecreto')
            ->label('Regenerar secreto')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Regenerar el secreto del webhook')
            ->modalDescription('ReservaYa dejará de poder firmar eventos hasta que le pases el nuevo valor. El secreto se muestra una sola vez.')
            ->action(function (Origin $record): void {
                $secret = Origin::generateWebhookSecret();

                $record->update(['webhook_secret' => $secret]);

                $this->fillForm();

                Notification::make()
                    ->title('Secreto regenerado')
                    ->body($secret)
                    ->persistent()
                    ->warning()
                    ->send();
            });
    }

    /**
     * @param  callable(): (bool|null)  $probe
     */
    private function probe(callable $probe): string
    {
        try {
            return match ($probe()) {
                true => 'ok',
                false => 'respondió con error',
                null => 'sin credenciales',
            };
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    }
}
