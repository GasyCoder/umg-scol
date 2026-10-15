import { Form } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { SettingsSection } from '@/layouts/settings/layout';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <SettingsSection
            icon={Trash2}
            tone="danger"
            title="Supprimer le compte"
            description="Supprimez votre compte et toutes les ressources associées"
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3 text-sm">
                    <AlertTriangle
                        className="mt-0.5 size-4 shrink-0 text-danger"
                        aria-hidden="true"
                    />
                    <div className="space-y-0.5">
                        <p className="font-semibold text-foreground">
                            Attention
                        </p>
                        <p className="text-muted-foreground">
                            Veuillez procéder avec prudence : cette action est
                            irréversible.
                        </p>
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            className="shrink-0"
                            data-test="delete-user-button"
                        >
                            <Trash2 aria-hidden="true" />
                            Supprimer le compte
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <span
                            className="flex size-11 items-center justify-center rounded-xl bg-danger-soft text-danger-foreground"
                            aria-hidden="true"
                        >
                            <AlertTriangle className="size-5" />
                        </span>
                        <DialogTitle>
                            Voulez-vous vraiment supprimer votre compte ?
                        </DialogTitle>
                        <DialogDescription>
                            Une fois votre compte supprimé, toutes ses
                            ressources et données seront également supprimées
                            définitivement. Veuillez saisir votre mot de passe
                            pour confirmer la suppression définitive de votre
                            compte.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Mot de passe
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Mot de passe"
                                            autoComplete="current-password"
                                            className="h-10"
                                        />

                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Annuler
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Supprimer le compte
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsSection>
    );
}
