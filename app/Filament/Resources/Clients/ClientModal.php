<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/**
 * One client modal (ADR-0010): the Clients list (create, edit) and the invoice's client field (create) use the
 * same heading, width and form (ClientForm), so the two never drift apart.
 */
final class ClientModal
{
    public const Width WIDTH = Width::FourExtraLarge;

    /**
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    public static function create(Action $action): Action
    {
        return $action->modalHeading('New client')->modalWidth(self::WIDTH)->modalSubmitActionLabel('Create client');
    }

    /**
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    public static function edit(Action $action): Action
    {
        return $action->modalHeading('Edit client')->modalWidth(self::WIDTH)->modalSubmitActionLabel('Save changes');
    }
}
