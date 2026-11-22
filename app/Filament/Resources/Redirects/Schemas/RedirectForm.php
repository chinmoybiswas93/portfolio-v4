<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('from_path')->label('From')->required()->maxLength(512)
                    ->startsWith('/')->unique(ignoreRecord: true)->placeholder('/old-page'),
                TextInput::make('to_path')->label('To')->required()->maxLength(512)
                    ->rule('regex:#^(/|https?://)#')->placeholder('/new-page or https://…')
                    ->helperText('A path on this site or a full https:// address.'),
                Select::make('status_code')->label('Type')->options([301 => '301 Permanent', 302 => '302 Temporary'])->default(301)->required(),
            ]),
        ]);
    }
}
