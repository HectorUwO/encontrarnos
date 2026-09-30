import { ActionIcon } from '@/Components/Encontrarnos/motion';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Ingresar" />
            <div className="en-auth-form-heading">
                <span>ACCESO A LA PLATAFORMA</span>
                <h2>Ingresar</h2>
                <p>
                    Accede a tu cuenta para consultar las herramientas
                    disponibles.
                </p>
            </div>
            {status && (
                <div className="en-auth-status" role="status">
                    {status}
                </div>
            )}
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
                        autoFocus
                        required
                        placeholder="tu@correo.com"
                    />
                    {errors.email && (
                        <span className="en-auth-error">{errors.email}</span>
                    )}
                </div>
                <div className="en-auth-field">
                    <div className="en-auth-field-row">
                        <label htmlFor="password">Contraseña</label>
                        {canResetPassword && (
                            <Link href={route('password.request')}>
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>
                    <div className="en-auth-password">
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            autoComplete="current-password"
                            required
                            placeholder="Ingresa tu contraseña"
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            aria-label={
                                showPassword
                                    ? 'Ocultar contraseña'
                                    : 'Mostrar contraseña'
                            }
                        >
                            {showPassword ? (
                                <EyeOff size={18} />
                            ) : (
                                <Eye size={18} />
                            )}
                        </button>
                    </div>
                    {errors.password && (
                        <span className="en-auth-error">{errors.password}</span>
                    )}
                </div>
                <label className="en-auth-check">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                    />
                    <span>Mantener la sesión iniciada</span>
                </label>
                <button
                    className="en-auth-submit"
                    type="submit"
                    disabled={processing}
                    aria-busy={processing}
                >
                    Ingresar a Encontrarnos <ActionIcon pending={processing} />
                </button>
            </form>
            <p className="en-auth-switch">
                ¿Aún no tienes una cuenta?{' '}
                <Link href={route('register')}>Crear cuenta</Link>
            </p>
        </GuestLayout>
    );
}
