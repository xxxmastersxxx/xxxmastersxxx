<?php

namespace App\Filament\Admin\Resources\SettingResource\Pages;

use App\Filament\Admin\Resources\SettingResource;
use App\Models\Setting;
use AymanAlhattami\FilamentPageWithSidebar\Traits\HasPageSidebar;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;

class DefaultSetting extends Page implements HasForms
{
    use HasPageSidebar, InteractsWithForms;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.setting-resource.pages.default-setting';

    /**
     * @dev @victormsalatiel
     * @param Model $record
     * @return bool
     */
    public static function canView(Model $record): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return string|Htmlable
     */
    public function getTitle(): string | Htmlable
    {
        return __('Padrão');
    }

    public Setting $record;
    public ?array $data = [];

    /**
     * @dev victormsalatiel - Meu instagram
     * @return void
     */
    public function mount(): void
    {
        try {
            $setting = Setting::first();
            if (!$setting) {
                Log::error('DefaultSetting: Nenhuma configuração encontrada');
                return;
            }
            
            $this->record = $setting;
            $this->form->fill($setting->toArray());
            
            Log::info('DefaultSetting: Configuração carregada com sucesso', ['setting_id' => $setting->id]);
        } catch (\Exception $e) {
            Log::error('DefaultSetting: Erro ao carregar configuração', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return void
     */
    public function saveRecord(): void
    {
        try {
            if(env('APP_DEMO')) {
                Notification::make()
                    ->title('Atenção')
                    ->body('Você não pode realizar está alteração na versão demo')
                    ->danger()
                    ->send();
                return;
            }

            $setting = Setting::find($this->record->id);
            if (!$setting) {
                Log::error('DefaultSetting: Configuração não encontrada para atualização');
                throw new \Exception('Configuração não encontrada');
            }

            // Processar uploads de arquivos
            $favicon = $this->data['software_favicon'] ?? null;
            $logoWhite = $this->data['software_logo_white'] ?? null;
            $logoBlack = $this->data['software_logo_black'] ?? null;

            // Upload do favicon
            if (is_array($favicon) || is_object($favicon)) {
                if(!empty($favicon)) {
                    $this->data['software_favicon'] = $this->uploadFile($favicon);
                    if(is_array($this->data['software_favicon'])) {
                        unset($this->data['software_favicon']);
                    }
                }
            }

            // Upload da logo branca
            if (is_array($logoWhite) || is_object($logoWhite)) {
                if(!empty($logoWhite)) {
                    $this->data['software_logo_white'] = $this->uploadFile($logoWhite);
                    if(is_array($this->data['software_logo_white'])) {
                        unset($this->data['software_logo_white']);
                    }
                }
            }

            // Upload da logo preta
            if (is_array($logoBlack) || is_object($logoBlack)) {
                if(!empty($logoBlack)) {
                    $this->data['software_logo_black'] = $this->uploadFile($logoBlack);
                    if(is_array($this->data['software_logo_black'])) {
                        unset($this->data['software_logo_black']);
                    }
                }
            }

            // Atualizar APP_NAME no arquivo .env
            try {
                $envs = DotenvEditor::load(base_path('.env'));
                $envs->setKeys([
                    'APP_NAME' => $this->data['software_name'],
                ]);
                $envs->save();
                Log::info('DefaultSetting: APP_NAME atualizado no .env', ['app_name' => $this->data['software_name']]);
            } catch (\Exception $e) {
                Log::error('DefaultSetting: Erro ao atualizar .env', ['error' => $e->getMessage()]);
            }

            // Atualizar configuração no banco
            if($setting->update($this->data)) {
                Cache::put('setting', $setting);

                Notification::make()
                    ->title('Dados alterados')
                    ->body('Dados alterados com sucesso!')
                    ->success()
                    ->send();

                Log::info('DefaultSetting: Configuração atualizada com sucesso', ['setting_id' => $setting->id]);

                redirect(Filament::getCurrentPanel()->getPath() . '/settings');
            } else {
                Log::error('DefaultSetting: Falha ao atualizar configuração');
                throw new \Exception('Falha ao atualizar configuração');
            }
        } catch (Halt $exception) {
            Log::error('DefaultSetting: Halt exception', ['error' => $exception->getMessage()]);
            return;
        } catch (\Exception $e) {
            Log::error('DefaultSetting: Erro geral', ['error' => $e->getMessage()]);
            Notification::make()
                ->title('Erro')
                ->body('Erro ao salvar configurações: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ajuste Visual')
                    ->description('Formulário ajustar o visual da plataforma')
                    ->schema([
                        Group::make()->schema([
                            TextInput::make('software_name')
                                ->label('Nome do Site')
                                ->placeholder('Digite o nome do site')
                                ->helperText('Este nome será usado como título do site')
                                ->required()
                                ->maxLength(191),
                            TextInput::make('software_description')
                                ->placeholder('Digite o link de Suporte')
                                ->label('Link de Suporte')
                                ->maxLength(191),
                        ])->columns(2),
                        Group::make()->schema([
                            FileUpload::make('software_favicon')
                                ->label('Favicon')
                                ->placeholder('Carregue um favicon')
                                ->image(),
                            Group::make()->schema([
                                FileUpload::make('software_logo_white')
                                    ->label('Logo branca (Img Nav)')
                                    ->placeholder('Carregue a logo branca para navegação')
                                    ->image()
                                    ->columnSpanFull(),
                                FileUpload::make('software_logo_black')
                                    ->label('Logo preta (Logo Loading)')
                                    ->placeholder('Carregue a logo preta para loading')
                                    ->image()
                                    ->columnSpanFull(),
                            ])
                        ])->columns(2),
                    ])
            ])
            ->statePath('data') ;
    }

    /**
     * @dev victormsalatiel - Meu instagram
     * @param $array
     * @return mixed|void
     */
    private function uploadFile($array)
    {
        if(!empty($array) && is_array($array) || !empty($array) && is_object($array)) {
            foreach ($array as $k => $temporaryFile) {
                if ($temporaryFile instanceof TemporaryUploadedFile) {
                    $path = $temporaryFile->store('public/uploads', 'public');
                    if($path) {
                        return $path;
                    }
                }else{
                    return $temporaryFile;
                }
            }
        }
    }
}
