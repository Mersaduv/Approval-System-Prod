import { Head } from "@inertiajs/react";
import AppLayout from "../Layouts/AppLayout";
import DelegationManagement from "../Components/DelegationManagement";

export default function DelegationManagementPage({ auth }) {
    return (
        <AppLayout auth={auth}>
            <Head title="Delegation Management" />
            <DelegationManagement auth={auth} />
        </AppLayout>
    );
}
