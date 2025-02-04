<?php

namespace App\Filament\Pages;

use App\Models\Team;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Wizard;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Actions\Action;

class CreateTeamAdmin extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.create-team-admin';
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Create Team Admin';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $title = 'Create New Team Admin';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Team Admin Details')
                        ->schema([
                            Section::make('User Information')
                                ->schema([
                                    TextInput::make('user.name')
                                        ->label('Name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('user.email')
                                        ->label('Email')
                                        ->email()
                                        ->required()
                                        ->unique(User::class, 'email'),
                                    TextInput::make('user.password')
                                        ->label('Password')
                                        ->password()
                                        ->required()
                                        ->minLength(8),
                                ])->columns(2),
                        ]),

                    Wizard\Step::make('Team Details')
                        ->schema([
                            Section::make('Team Information')
                                ->schema([
                                    TextInput::make('team.name')
                                        ->label('Team Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(Team::class, 'name'),
                                    TextInput::make('team.description')
                                        ->label('Team Description')
                                        ->maxLength(255),
                                ]),
                        ]),


                ])
                ->submitAction(
                    Action::make('create')
                        ->label('Create Team Admin')
                        ->submit('create')
                        ->color('primary')
                )
            ])
            ->statePath('data');
    }

    public function create()
    {
        $state = $this->form->getState();

        try {
            \DB::beginTransaction();

            // Create User
            $user = User::create([
                'name' => $state['user']['name'],
                'email' => $state['user']['email'],
                'password' => Hash::make($state['user']['password']),
            ]);

            // Assign Role
            $teamAdminRole = Role::where('name', 'team_admin')->firstOrFail();
            $user->assignRole($teamAdminRole);

            // Create Team
            $team = Team::create([
                'name' => $state['team']['name'],
                'description' => $state['team']['description'],
                'slug' => Str::slug($state['team']['name']),
                'created_by' => auth()->id(),
            ]);

            // Attach User to Team
            $team->users()->attach($user->id);

            \DB::commit();

            Notification::make()
                ->title('Team Admin created successfully')
                ->success()
                ->send();

            $this->redirect('/bdsmanager/');

        } catch (\Exception $e) {
            \DB::rollBack();

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }
}
