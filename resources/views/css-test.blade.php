<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSS Test</title>
    @vite(['resources/css/test.css'])
</head>
<body>
    <div class="min-h-screen bg-gray-100 p-8">
        <h1 class="text-4xl font-bold text-blue-600 mb-6">CSS Test</h1>

        <!-- Simple CSS Test -->
        <div class="test-red">
            This text should be displayed with a red background (Simple CSS)
        </div>

        <div class="test-blue">
            This text should be displayed with a blue background (Simple CSS)
        </div>

        <!-- Tailwind Test -->
        <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
            This text should be displayed with a red background (Tailwind)
        </div>

        <div class="bg-green-500 text-white p-4 rounded-lg mb-4">
            This text should be displayed with a green background (Tailwind)
        </div>

        <div class="bg-yellow-500 text-black p-4 rounded-lg mb-4">
            This text should be displayed with a yellow background (Tailwind)
        </div>

        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Test Button
        </button>
    </div>
</body>
</html>
