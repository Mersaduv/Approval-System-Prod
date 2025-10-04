<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tailwind CSS Test</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <div class="min-h-screen bg-gray-100 p-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl font-bold text-blue-600 mb-6">
                Tailwind CSS Test
            </h1>

            <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
                <p class="text-lg">This text should be displayed with a red background</p>
            </div>

            <div class="bg-green-500 text-white p-4 rounded-lg mb-4">
                <p class="text-lg">This text should be displayed with a green background</p>
            </div>

            <div class="bg-yellow-500 text-black p-4 rounded-lg mb-4">
                <p class="text-lg">This text should be displayed with a yellow background</p>
            </div>

            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Test Button
            </button>
        </div>
    </div>
</body>
</html>
