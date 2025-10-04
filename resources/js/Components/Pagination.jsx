import React from 'react'

const Pagination = ({
    pagination,
    onPageChange,
    onPerPageChange,
    showPerPageSelector = true,
    perPageOptions = [10, 25, 50],
    className = ""
}) => {
    if (!pagination || pagination.last_page <= 1) {
        return null
    }

    const { current_page, last_page, per_page, total, from, to } = pagination

    // Generate page numbers to show
    const getPageNumbers = () => {
        const pages = []
        const maxVisiblePages = 5

        if (last_page <= maxVisiblePages) {
            // Show all pages if total pages is less than or equal to maxVisiblePages
            for (let i = 1; i <= last_page; i++) {
                pages.push(i)
            }
        } else {
            // Always show first page
            pages.push(1)

            let start = Math.max(2, current_page - 1)
            let end = Math.min(last_page - 1, current_page + 1)

            // Adjust start and end to show exactly maxVisiblePages - 2 pages (excluding first and last)
            if (current_page <= 3) {
                end = Math.min(last_page - 1, maxVisiblePages - 1)
            } else if (current_page >= last_page - 2) {
                start = Math.max(2, last_page - maxVisiblePages + 2)
            }

            // Add ellipsis if there's a gap after first page
            if (start > 2) {
                pages.push('...')
            }

            // Add middle pages
            for (let i = start; i <= end; i++) {
                pages.push(i)
            }

            // Add ellipsis if there's a gap before last page
            if (end < last_page - 1) {
                pages.push('...')
            }

            // Always show last page
            if (last_page > 1) {
                pages.push(last_page)
            }
        }

        return pages
    }

    const pageNumbers = getPageNumbers()

    return (
        <div className={`flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 ${className}`}>
            {/* Results info */}
            <div className="text-sm text-gray-700">
                Showing <span className="font-medium">{from || 0}</span> to{' '}
                <span className="font-medium">{to || 0}</span> of{' '}
                <span className="font-medium">{total}</span> results
            </div>

            <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                {/* Per page selector */}
                {showPerPageSelector && (
                    <div className="flex items-center gap-2">
                        <label htmlFor="per-page" className="text-sm text-gray-700">
                            Show:
                        </label>
                        <select
                            id="per-page"
                            value={per_page}
                            onChange={(e) => onPerPageChange(parseInt(e.target.value))}
                            className="block w-20 px-2 py-1 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        >
                            {perPageOptions.map(option => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Pagination controls */}
                <nav className="flex items-center gap-1">
                    {/* Previous button */}
                    <button
                        onClick={() => onPageChange(current_page - 1)}
                        disabled={current_page <= 1}
                        className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white"
                        aria-label="Previous page"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    {/* Page numbers */}
                    {pageNumbers.map((page, index) => (
                        <React.Fragment key={index}>
                            {page === '...' ? (
                                <span className="px-3 py-2 text-sm font-medium text-gray-700 bg-white border-t border-b border-gray-300">
                                    ...
                                </span>
                            ) : (
                                <button
                                    onClick={() => onPageChange(page)}
                                    className={`px-3 py-2 text-sm font-medium border-t border-b border-gray-300 ${
                                        page === current_page
                                            ? 'bg-blue-50 text-blue-600 border-blue-500 z-10'
                                            : 'bg-white text-gray-500 hover:bg-gray-50'
                                    }`}
                                >
                                    {page}
                                </button>
                            )}
                        </React.Fragment>
                    ))}

                    {/* Next button */}
                    <button
                        onClick={() => onPageChange(current_page + 1)}
                        disabled={current_page >= last_page}
                        className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white"
                        aria-label="Next page"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    )
}

export default Pagination
