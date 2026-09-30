import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ status }: PageProps<{ status?: string }>) {
    return (
        <WorkspaceLayout active="profile" crumb="PANEL / MI CUENTA">
            <Head title="Mi cuenta" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">
                            ENCONTRARNOS / MI CUENTA
                        </span>
                        <h1>TUS DATOS.</h1>
                        <p>
                            Actualiza tu nombre y correo, cambia tu contraseña o
                            elimina tu cuenta.
                        </p>
                    </div>
                </div>
                <div className="en-profile-grid">
                    <UpdateProfileInformationForm status={status} />
                    <UpdatePasswordForm />
                    <DeleteUserForm />
                </div>
            </main>
        </WorkspaceLayout>
    );
}
