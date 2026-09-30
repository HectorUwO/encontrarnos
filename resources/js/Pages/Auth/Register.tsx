import { ActionIcon } from '@/Components/Encontrarnos/motion';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Crear cuenta" />
            <div className="en-auth-form-heading">
                <span>NUEVA CUENTA</span>
                <h2>Crear cuenta</h2>
                <p>Completa tus datos para acceder a la plataforma.</p>
            </div>
            <form onSubmit={submit} className="en-auth-form">
                <div className="en-auth-field">
                    <label htmlFor="name">Nombre completo</label>
                    <input
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        autoComplete="name"
                        autoFocus
                        required
                        placeholder="Tu nombre"
                    />
                    {errors.name && (
                        <span className="en-auth-error">{errors.name}</span>
                    )}
                </div>
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
                        placeholder="tu@correo.com"
                    />
                    {errors.email && (
                        <span className="en-auth-error">{errors.email}</span>
                    )}
                </div>
                <div className="en-auth-field">
                    <label htmlFor="password">Contraseña</label>
                    <div className="en-auth-password">
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            autoComplete="new-password"
                            required
                            placeholder="Crea una contraseña"
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
                <div className="en-auth-field">
                    <label htmlFor="password_confirmation">
                        Confirmar contraseña
                    </label>
                    <input
                        id="password_confirmation"
                        type={showPassword ? 'text' : 'password'}
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(event) =>
                            setData('password_confirmation', event.target.value)
                        }
                        autoComplete="new-password"
                        required
                        placeholder="Repite la contraseña"
                    />
                    {errors.password_confirmation && (
                        <span className="en-auth-error">
                            {errors.password_confirmation}
                        </span>
                    )}
                </div>
                <button
                    className="en-auth-submit"
                    type="submit"
                    disabled={processing}
                    aria-busy={processing}
                >
                    Crear cuenta <ActionIcon pending={processing} />
                </button>
            </form>
            <p className="en-auth-switch">
                ¿Ya tienes una cuenta?{' '}
                <Link href={route('login')}>Ingresar</Link>
            </p>
        </GuestLayout>
    );
}
