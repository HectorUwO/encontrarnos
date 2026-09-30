import { ActionIcon } from '@/Components/Encontrarnos/motion';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Nueva contraseña" />
            <div className="en-auth-form-heading">
                <span>SEGURIDAD DE LA CUENTA</span>
                <h2>Nueva contraseña</h2>
                <p>Establece una contraseña nueva para volver a ingresar.</p>
            </div>
            <form onSubmit={submit} className="en-auth-form">
                <div className="en-auth-field">
                    <label htmlFor="email">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        autoComplete="username"
                        required
                    />
                    {errors.email && (
                        <span className="en-auth-error">{errors.email}</span>
                    )}
                </div>
                <div className="en-auth-field">
                    <label htmlFor="password">Nueva contraseña</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        autoComplete="new-password"
                        autoFocus
                        required
                    />
                    {errors.password && (
                        <span className="en-auth-error">{errors.password}</span>
                    )}
                </div>
                <div className="en-auth-field">
                    <label htmlFor="password_confirmation">
                        Confirmar contraseña
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(event) =>
                            setData('password_confirmation', event.target.value)
                        }
                        autoComplete="new-password"
                        required
                    />
                    {errors.password_confirmation && (
                        <span className="en-auth-error">
                            {errors.password_confirmation}
                        </span>
                    )}
                </div>
                <button
                    type="submit"
                    className="en-auth-submit"
                    disabled={processing}
                    aria-busy={processing}
                >
                    Guardar contraseña <ActionIcon pending={processing} />
                </button>
            </form>
        </GuestLayout>
    );
}
