import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({ password: '' });

    const cancel = () => {
        setConfirming(false);
        clearErrors();
        reset();
    };

    const deleteUser: FormEventHandler = (event) => {
        event.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <section className="en-profile-card en-profile-danger">
            <header>
                <span className="en-work-kicker">ZONA DE RIESGO</span>
                <h2>Eliminar cuenta</h2>
                <p>
                    Al eliminar tu cuenta se borrarán tus datos y tus
                    solicitudes. Esta acción no se puede deshacer.
                </p>
            </header>
            {confirming ? (
                <form onSubmit={deleteUser} className="en-profile-form">
                    <div className="en-profile-field">
                        <label htmlFor="delete_password">
                            Escribe tu contraseña para confirmar
                        </label>
                        <input
                            id="delete_password"
                            ref={passwordInput}
                            type="password"
                            value={data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            autoComplete="current-password"
                            autoFocus
                            required
                        />
                        {errors.password && (
                            <span className="en-profile-error">
                                {errors.password}
                            </span>
                        )}
                    </div>
                    <div className="en-profile-actions">
                        <button
                            type="submit"
                            className="en-profile-button en-profile-button-danger"
                            disabled={processing}
                        >
                            Sí, eliminar mi cuenta
                        </button>
                        <button
                            type="button"
                            className="en-profile-button en-profile-button-ghost"
                            onClick={cancel}
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            ) : (
                <div className="en-profile-actions">
                    <button
                        type="button"
                        className="en-profile-button en-profile-button-danger"
                        onClick={() => setConfirming(true)}
                    >
                        Eliminar cuenta
                    </button>
                </div>
            )}
        </section>
    );
}
