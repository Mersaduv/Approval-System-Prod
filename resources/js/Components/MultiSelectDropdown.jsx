import { useState, useRef, useEffect } from 'react'

export default function MultiSelectDropdown({
    options = [],
    selectedValues = [],
    onChange,
    placeholder = "Select options...",
    className = "",
    error = null,
    disabled = false
}) {
    const [isOpen, setIsOpen] = useState(false)
    const [searchTerm, setSearchTerm] = useState('')
    const dropdownRef = useRef(null)

    // Filter options based on search term
    const filteredOptions = options.filter(option =>
        option.name.toLowerCase().includes(searchTerm.toLowerCase())
    )

    // Handle option toggle
    const handleOptionToggle = (optionId) => {
        if (disabled) return

        const newSelectedValues = selectedValues.includes(optionId)
            ? selectedValues.filter(id => id !== optionId)
            : [...selectedValues, optionId]

        onChange(newSelectedValues)
    }

    // Handle select all
    const handleSelectAll = () => {
        if (disabled) return

        const allOptionIds = filteredOptions.map(option => option.id)
        const allSelected = allOptionIds.every(id => selectedValues.includes(id))

        if (allSelected) {
            // Deselect all filtered options
            const newSelectedValues = selectedValues.filter(id => !allOptionIds.includes(id))
            onChange(newSelectedValues)
        } else {
            // Select all filtered options
            const newSelectedValues = [...new Set([...selectedValues, ...allOptionIds])]
            onChange(newSelectedValues)
        }
    }

    // Get selected options for display
    const selectedOptions = options.filter(option => selectedValues.includes(option.id))

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false)
                setSearchTerm('')
            }
        }

        document.addEventListener('mousedown', handleClickOutside)
        return () => document.removeEventListener('mousedown', handleClickOutside)
    }, [])

    return (
        <div className={`multi-select-dropdown ${className}`} ref={dropdownRef}>
            {/* Dropdown Button */}
            <button
                type="button"
                onClick={() => !disabled && setIsOpen(!isOpen)}
                disabled={disabled}
                className={`dropdown-button relative w-full px-3 py-2 text-left bg-white border rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    error ? 'border-red-300' : 'border-gray-300'
                } ${disabled ? 'bg-gray-50 cursor-not-allowed' : 'cursor-pointer'}`}
            >
                <span className="block truncate">
                    {selectedOptions.length === 0
                        ? placeholder
                        : selectedOptions.length === 1
                            ? selectedOptions[0].name
                            : `${selectedOptions.length} roles selected`
                    }
                </span>
                <span className="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                    <svg
                        className={`dropdown-arrow w-5 h-5 text-gray-400 ${
                            isOpen ? 'rotated' : ''
                        }`}
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            </button>

            {/* Dropdown Panel */}
            {isOpen && (
                <div className="dropdown-panel absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-80 overflow-hidden">
                    {/* Search Input */}
                    <div className="p-2 border-b border-gray-200">
                        <input
                            type="text"
                            placeholder="Search roles..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="search-input w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                            autoFocus
                        />
                    </div>

                    {/* Select All Option */}
                    {filteredOptions.length > 0 && (
                        <div className="select-all-option px-3 py-2 border-b border-gray-200">
                            <label className="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={filteredOptions.every(option => selectedValues.includes(option.id))}
                                    onChange={handleSelectAll}
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <span className="ml-2 text-sm font-medium text-gray-700">
                                    Select All
                                </span>
                            </label>
                        </div>
                    )}

                    {/* Options List */}
                    <div className="max-h-64 overflow-y-auto">
                        {filteredOptions.length === 0 ? (
                            <div className="px-3 py-2 text-sm text-gray-500">
                                {searchTerm ? 'No roles found' : 'No roles available'}
                            </div>
                        ) : (
                            filteredOptions.map((option) => (
                                <label
                                    key={option.id}
                                    className="dropdown-option flex items-center cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedValues.includes(option.id)}
                                        onChange={() => handleOptionToggle(option.id)}
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span className="ml-2 text-sm text-gray-700">
                                        {option.name}
                                    </span>
                                </label>
                            ))
                        )}
                    </div>
                </div>
            )}

            {/* Error Message */}
            {error && (
                <p className="mt-1 text-sm text-red-600">{error}</p>
            )}
        </div>
    )
}
