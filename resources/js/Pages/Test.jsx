import AppLayout from '../Layouts/AppLayout'

export default function Test() {
    return (
        <AppLayout title="Tailwind Test">
            <div className="max-w-4xl mx-auto p-8">
                <h1 className="text-4xl font-bold text-blue-600 mb-6">
                    Tailwind CSS Test
                </h1>

                <div className="bg-red-500 text-white p-4 rounded-lg mb-4">
                    <p className="text-lg">This text should be displayed with a red background</p>
                </div>

                <div className="bg-green-500 text-white p-4 rounded-lg mb-4">
                    <p className="text-lg">This text should be displayed with a green background</p>
                </div>

                <div className="bg-yellow-500 text-black p-4 rounded-lg mb-4">
                    <p className="text-lg">This text should be displayed with a yellow background</p>
                </div>

                <button className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Test Button
                </button>
            </div>
        </AppLayout>
    )
}
