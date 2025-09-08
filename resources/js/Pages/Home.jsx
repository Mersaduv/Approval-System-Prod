import AppLayout from '../Layouts/AppLayout'

export default function Home({ auth }) {
    return (
        <AppLayout title="Home" auth={auth}>
            <div className="max-w-4xl mx-auto">
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-gray-900 mb-6">
                        Approval Workflow Management System
                    </h1>
                    <p className="text-xl text-gray-600 mb-8">
                        Welcome to the Approval Workflow Management System
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow-md p-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-4">
                        About the System
                    </h2>
                    <p className="text-gray-600 leading-relaxed mb-6">
                        This system is designed for managing approval workflows.
                        Using this system, you can define and manage various
                        approval processes efficiently.
                    </p>

                    <h3 className="text-xl font-semibold text-gray-800 mb-3">
                        Key Features:
                    </h3>
                    <ul className="list-disc list-inside text-gray-600 space-y-2">
                        <li>Define custom approval processes</li>
                        <li>Manage different approval stages</li>
                        <li>Track request status</li>
                        <li>Automatic notifications</li>
                        <li>Comprehensive reporting</li>
                    </ul>
                </div>

                <div className="mt-8 text-center">
                    <p className="text-gray-500">
                        To get started, use the navigation menu above
                    </p>
                </div>
            </div>
        </AppLayout>
    )
}
