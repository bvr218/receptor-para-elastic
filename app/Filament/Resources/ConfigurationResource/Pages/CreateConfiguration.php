<?php

namespace App\Filament\Resources\ConfigurationResource\Pages;

use App\Filament\Resources\ConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Configuration;
use Filament\Forms\Form;
use Elastic\Elasticsearch\ClientBuilder;

use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions as FormActions;


class CreateConfiguration extends CreateRecord
{
    protected static string $resource = ConfigurationResource::class;

    public $validated = false;

    protected function getFormActions(): array
    {
        return [
            
        ];
    }
    public function getTitle(): string
    {
        return '';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make("Configuraciones")
                        ->schema([
                            Components\TextInput::make("elastic_host")
                                ->label("Url de servidor elastic")
                                ->prefix("https://")
                                ->live()
                                ->afterStateUpdated(function ($state) {
                                    $this->validated = false;
                                })
                                ->default(function(){
                                    $config = Configuration::where("name","elastic_host")->first();
                                    if(is_null($config)){
                                        return "";
                                    }
                                    return $config->value;
                                }),
                            Components\Grid::make(2)
                                ->schema([
                                    Components\TextInput::make("elastic_user")
                                        ->label("Usuario Elastic")
                                        ->live()
                                        ->afterStateUpdated(function ($state) {
                                            $this->validated = false;
                                        })
                                        ->default(function(){
                                            $config = Configuration::where("name","elastic_user")->first();
                                            if(is_null($config)){
                                                return "";
                                            }
                                            return $config->value;
                                        }),
                                    Components\TextInput::make("elastic_password")
                                        ->label("Contraseña Elastic")
                                        ->password()
                                        ->revealable()
                                        ->live()
                                        ->afterStateUpdated(function ($state) {
                                            $this->validated = false;
                                        })
                                        ->default(function(){
                                            $config = Configuration::where("name","elastic_password")->first();
                                            if(is_null($config)){
                                                return "";
                                            }
                                            return $config->value;
                                        }),

                                ])

                        ]),
                    FormActions::make([
                        FormActions\Action::make("Validar")
                            
                            ->action(function($state){
                                try {
                                    $client = ClientBuilder::create()
                                        ->setHosts(["https://".$state["elastic_host"]])
                                        ->setSSLVerification(false)
                                        ->setBasicAuthentication($state["elastic_user"], $state["elastic_password"])
                                        ->build();
                                
                                    // Validar conexión al host usando ping
                                    if ($client->ping()) {
                                        Notification::make()
                                            ->success()
                                            ->title("La configuracion es válida.")
                                            ->send();
                                            $this->validated = true;
                                    } else {
                                        Notification::make()
                                            ->warning()
                                            ->title("La configuracion es inválida.")
                                            ->send();
                                            $this->validated = false;
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()
                                            ->warning()
                                            ->title("La configuracion es inválida.")
                                            ->send();
                                            $this->validated = false;
                                }
                                
                            }),
                        FormActions\Action::make("Guardar")
                            ->disabled(function(){
                                return !$this->validated;
                            })
                            ->action(function($state){
                                $config = Configuration::where("name","elastic_host")->first();
                                if(is_null($config)){
                                    Configuration::create([
                                        "name"=>"elastic_host",
                                        "value"=>$state["elastic_host"]
                                    ]);

                                }else{
                                    $config->value = $state["elastic_host"];
                                }

                                $config = Configuration::where("name","elastic_user")->first();
                                if(is_null($config)){
                                    Configuration::create([
                                        "name"=>"elastic_user",
                                        "value"=>$state["elastic_user"]
                                    ]);

                                }else{
                                    $config->value = $state["elastic_user"];
                                }

                                $config = Configuration::where("name","elastic_password")->first();
                                if(is_null($config)){
                                    Configuration::create([
                                        "name"=>"elastic_password",
                                        "value"=>$state["elastic_password"]
                                    ]);

                                }else{
                                    $config->value = $state["elastic_password"];
                                }

                                Notification::make()
                                    ->success()
                                    ->title("Configuración actualizada.")
                                    ->send();
                            })
                ])
            ]);
    }
}
