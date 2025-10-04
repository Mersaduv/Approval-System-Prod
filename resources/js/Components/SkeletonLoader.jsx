import React from 'react'

// Skeleton component for table rows
export const TableRowSkeleton = ({ columns = 8, rows = 5 }) => {
    return (
        <>
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <tr key={rowIndex} className="animate-pulse">
                    {Array.from({ length: columns }).map((_, colIndex) => (
                        <td key={colIndex} className="px-6 py-4 whitespace-nowrap">
                            <div className="h-4 bg-gray-200 rounded w-full"></div>
                        </td>
                    ))}
                </tr>
            ))}
        </>
    )
}

// Skeleton component for mobile cards
export const CardSkeleton = ({ count = 5 }) => {
    return (
        <>
            {Array.from({ length: count }).map((_, index) => (
                <div key={index} className="bg-white shadow-sm rounded-lg p-4 border border-gray-200 animate-pulse">
                    <div className="flex items-start justify-between mb-3">
                        <div className="flex-1 min-w-0">
                            <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                            <div className="h-3 bg-gray-200 rounded w-1/4"></div>
                        </div>
                        <div className="h-6 bg-gray-200 rounded-full w-20"></div>
                    </div>

                    <div className="space-y-2">
                        <div className="flex justify-between">
                            <div className="h-4 bg-gray-200 rounded w-16"></div>
                            <div className="h-4 bg-gray-200 rounded w-20"></div>
                        </div>
                        <div className="flex justify-between">
                            <div className="h-4 bg-gray-200 rounded w-20"></div>
                            <div className="h-4 bg-gray-200 rounded w-16"></div>
                        </div>
                        <div className="flex justify-between">
                            <div className="h-4 bg-gray-200 rounded w-16"></div>
                            <div className="h-4 bg-gray-200 rounded w-24"></div>
                        </div>
                        <div className="flex justify-between">
                            <div className="h-4 bg-gray-200 rounded w-12"></div>
                            <div className="h-4 bg-gray-200 rounded w-20"></div>
                        </div>
                    </div>

                    <div className="mt-4 pt-3 border-t border-gray-200">
                        <div className="h-8 bg-gray-200 rounded w-full"></div>
                    </div>
                </div>
            ))}
        </>
    )
}

// Skeleton component for delegation list items
export const DelegationItemSkeleton = ({ count = 5 }) => {
    return (
        <>
            {Array.from({ length: count }).map((_, index) => (
                <li key={index} className="px-6 py-4 animate-pulse">
                    <div className="flex items-center justify-between">
                        <div className="flex-1">
                            <div className="flex items-center space-x-3">
                                <div className="h-4 bg-gray-200 rounded w-32"></div>
                                <div className="h-6 bg-gray-200 rounded-full w-16"></div>
                            </div>
                            <div className="mt-2 space-y-2">
                                <div className="h-3 bg-gray-200 rounded w-3/4"></div>
                                <div className="h-3 bg-gray-200 rounded w-1/2"></div>
                                <div className="h-3 bg-gray-200 rounded w-2/3"></div>
                            </div>
                        </div>
                        <div className="flex space-x-2">
                            <div className="h-8 bg-gray-200 rounded w-16"></div>
                            <div className="h-8 bg-gray-200 rounded w-20"></div>
                        </div>
                    </div>
                </li>
            ))}
        </>
    )
}

// Skeleton component for workflow step
export const WorkflowStepSkeleton = () => {
    return (
        <div className="border border-gray-200 rounded-lg p-4 bg-white animate-pulse">
            <div className="flex justify-between items-start">
                <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="h-8 w-8 bg-gray-200 rounded"></div>
                        <div className="h-6 bg-gray-200 rounded w-8"></div>
                        <div className="h-6 bg-gray-200 rounded w-32"></div>
                        <div className="h-6 bg-gray-200 rounded-full w-16"></div>
                        <div className="h-6 bg-gray-200 rounded-full w-20"></div>
                    </div>
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-3"></div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div className="h-4 bg-gray-200 rounded w-24"></div>
                        <div className="h-4 bg-gray-200 rounded w-20"></div>
                    </div>
                    <div className="mt-3">
                        <div className="h-3 bg-gray-200 rounded w-32 mb-2"></div>
                        <div className="flex flex-wrap gap-2">
                            <div className="h-6 bg-gray-200 rounded w-16"></div>
                            <div className="h-6 bg-gray-200 rounded w-20"></div>
                            <div className="h-6 bg-gray-200 rounded w-14"></div>
                        </div>
                    </div>
                </div>
                <div className="flex gap-2">
                    <div className="h-8 w-8 bg-gray-200 rounded"></div>
                    <div className="h-8 w-8 bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>
    )
}

export default {
    TableRowSkeleton,
    CardSkeleton,
    DelegationItemSkeleton,
    WorkflowStepSkeleton
}
