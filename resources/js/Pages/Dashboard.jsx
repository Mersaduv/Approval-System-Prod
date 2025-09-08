import AppLayout from '../Layouts/AppLayout'

export default function Dashboard() {
    return (
        <AppLayout title="Dashboard">
            <div className="max-w-4xl mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-4">
                        System Dashboard
                    </h1>
                    <p className="text-lg text-gray-600">
                        This is the main page of the approval workflow management system
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow-md p-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">
                        System Overview
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-blue-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold text-blue-800 mb-2">
                                System Status
                            </h3>
                            <p className="text-blue-600">
                                System is running and ready for use
                            </p>
                        </div>

                        <div className="bg-green-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold text-green-800 mb-2">
                                Last Update
                            </h3>
                            <p className="text-green-600">
                                System is up to date and ready for use
                            </p>
                        </div>
                    </div>

                    <div className="mt-8">
                        <h3 className="text-xl font-semibold text-gray-800 mb-4">
                            Available Features
                        </h3>
                        <div className="space-y-3">
                            <div className="flex items-center space-x-3">
                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span className="text-gray-700">View Processes</span>
                            </div>
                            <div className="flex items-center space-x-3">
                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span className="text-gray-700">Manage Requests</span>
                            </div>
                            <div className="flex items-center space-x-3">
                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span className="text-gray-700">Generate Reports</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
