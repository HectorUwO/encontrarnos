import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (event) => {
        event.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className="en-profile-card">
            <header>
                <span className="en-work-kicker">SEGURIDAD</span>
                <h2>Cambiar contraseña</h2>
                <p>Usa una contraseña larga y única para proteger tu cuenta.</p>
            </header>
            {recentlySuccessful && (
                <div className="en-profile-status" role="status">
                    Actualizamos tu contraseña.
                </div>
            )}
            <form onSubmit={updatePassword} className="en-profile-form">
                <div className="en-profile-field">
                    <label htmlFor="current_password">Contraseña actual</label>
                    <input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        onChange={(event) =>
                            setData('current_password', event.target.value)
                        }
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <span className="en-profile-error">
                            {errors.current_password}
                        </span>
                    )}
                </div>
                <div className="en-profile-field">
                    <label htmlFor="password">Contraseña nueva</label>
                    <input
                        id="password"
                        ref={passwordInput}
                        type="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <span className="en-profile-error">
                            {errors.password}
                        </span>
                    )}
                </div>
                <div className="en-profile-field">
                    <label htmlFor="password_confirmation">
                        Confirmar contraseña nueva
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) =>
                            setData('password_confirmation', event.target.value)
                        }
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <span className="en-profile-error">
                            {errors.password_confirmation}
                        </span>
                    )}
                </div>
                <div className="en-profile-actions">
                    <button
                        type="submit"
                        className="en-profile-button"
                        disabled={processing}
                    >
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </section>
    );
}
