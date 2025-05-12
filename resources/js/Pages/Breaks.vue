<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { onMounted, ref } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import { CoffeeIcon } from '@heroicons/vue/24/outline';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { PlusIcon } from '@heroicons/vue/16/solid';
import { useToast } from '@/utils/useToast';

const currentTimeEntryStore = useCurrentTimeEntryStore();
const { currentTimeEntry } = storeToRefs(currentTimeEntryStore);
const toast = useToast();

// Points system
const pointsInfo = ref({
    total_points: 0,
    spent_points: 0,
    available_points: 0,
});

// Breaks
const availableBreaks = ref([]);
const redeemedBreaks = ref([]);
const loading = ref(false);
const showCreateBreakModal = ref(false);

// New break form
const newBreak = ref({
    description: '',
    points_cost: 10,
    duration_minutes: 5,
});

// Form validation
const errors = ref({
    description: '',
    points_cost: '',
    duration_minutes: '',
});

async function fetchPointsInfo() {
    try {
        const response = await axios.get('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/points');
        pointsInfo.value = response.data;
    } catch (error) {
        console.error('Error fetching points info:', error);
    }
}

async function fetchBreaks() {
    loading.value = true;
    try {
        const availableResponse = await axios.get('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/breaks');
        availableBreaks.value = availableResponse.data.data;

        const redeemedResponse = await axios.get('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/breaks/redeemed');
        redeemedBreaks.value = redeemedResponse.data.data;
    } catch (error) {
        console.error('Error fetching breaks:', error);
    } finally {
        loading.value = false;
    }
}

async function createBreak() {
    // Reset errors
    errors.value = {
        description: '',
        points_cost: '',
        duration_minutes: '',
    };

    // Validate form
    let hasErrors = false;
    if (!newBreak.value.description) {
        errors.value.description = 'Description is required';
        hasErrors = true;
    }
    if (!newBreak.value.points_cost || newBreak.value.points_cost < 1) {
        errors.value.points_cost = 'Points cost must be at least 1';
        hasErrors = true;
    }
    if (!newBreak.value.duration_minutes || newBreak.value.duration_minutes < 1) {
        errors.value.duration_minutes = 'Duration must be at least 1 minute';
        hasErrors = true;
    }

    if (hasErrors) {
        return;
    }

    try {
        await axios.post('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/breaks', newBreak.value);
        showCreateBreakModal.value = false;
        newBreak.value = {
            description: '',
            points_cost: 10,
            duration_minutes: 5,
        };
        await fetchBreaks();
        toast.success('Break created successfully');
    } catch (error) {
        console.error('Error creating break:', error);
        toast.error('Failed to create break');
    }
}

async function redeemBreak(breakId) {
    try {
        await axios.post('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/breaks/' + breakId + '/redeem');
        await fetchBreaks();
        await fetchPointsInfo();
        toast.success('Break redeemed successfully! Enjoy your break!');
    } catch (error) {
        console.error('Error redeeming break:', error);
        if (error.response && error.response.data && error.response.data.errors) {
            toast.error(error.response.data.errors.points[0] || 'Failed to redeem break');
        } else {
            toast.error('Failed to redeem break');
        }
    }
}

onMounted(async () => {
    await fetchPointsInfo();
    await fetchBreaks();
});

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString();
}
</script>

<template>
    <AppLayout title="Study Breaks" data-testid="breaks_view">
        <MainContainer class="pt-5 lg:pt-8 pb-4 lg:pb-6">
            <div class="mb-4 p-4 bg-green-50 rounded-lg border border-green-200">
                <h2 class="text-lg font-semibold text-green-800">Your Study Points</h2>
                <div class="flex flex-wrap mt-2 gap-4">
                    <div class="bg-white p-3 rounded-md shadow-sm flex-1">
                        <div class="text-sm text-gray-500">Total Points Earned</div>
                        <div class="text-xl font-bold text-green-600">{{ pointsInfo.total_points }}</div>
                    </div>
                    <div class="bg-white p-3 rounded-md shadow-sm flex-1">
                        <div class="text-sm text-gray-500">Points Spent</div>
                        <div class="text-xl font-bold text-orange-500">{{ pointsInfo.spent_points }}</div>
                    </div>
                    <div class="bg-white p-3 rounded-md shadow-sm flex-1">
                        <div class="text-sm text-gray-500">Available Points</div>
                        <div class="text-xl font-bold text-blue-600">{{ pointsInfo.available_points }}</div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Available Breaks</h2>
                <SecondaryButton
                    class="text-center flex justify-center"
                    :icon="PlusIcon"
                    @click="showCreateBreakModal = true"
                    >Create Break
                </SecondaryButton>
            </div>

            <div v-if="loading" class="flex justify-center items-center py-10">
                <LoadingSpinner></LoadingSpinner>
                <span class="ml-2">Loading breaks...</span>
            </div>

            <div v-else-if="availableBreaks.length === 0" class="text-center py-10 bg-gray-50 rounded-lg">
                <CoffeeIcon class="w-12 h-12 mx-auto text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No breaks available</h3>
                <p class="mt-1 text-sm text-gray-500">Create a break to redeem with your study points.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div v-for="breakItem in availableBreaks" :key="breakItem.id" class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h3 class="font-semibold text-lg">{{ breakItem.description }}</h3>
                    <div class="mt-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span>Duration:</span>
                            <span class="font-medium">{{ breakItem.duration_minutes }} minutes</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span>Points Cost:</span>
                            <span class="font-medium text-blue-600">{{ breakItem.points_cost }} points</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <PrimaryButton
                            class="w-full"
                            @click="redeemBreak(breakItem.id)"
                            :disabled="pointsInfo.available_points < breakItem.points_cost"
                        >
                            Redeem Break
                        </PrimaryButton>
                        <div v-if="pointsInfo.available_points < breakItem.points_cost" class="mt-2 text-xs text-red-500 text-center">
                            Not enough points
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="text-xl font-bold mt-8 mb-4">Redeemed Breaks</h2>

            <div v-if="redeemedBreaks.length === 0" class="text-center py-10 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">No redeemed breaks yet</h3>
                <p class="mt-1 text-sm text-gray-500">Redeem a break to see it here.</p>
            </div>

            <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Redeemed At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="breakItem in redeemedBreaks" :key="breakItem.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ breakItem.description }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ breakItem.duration_minutes }} minutes</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ breakItem.points_cost }} points</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(breakItem.redeemed_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </MainContainer>

        <!-- Create Break Modal -->
        <div v-if="showCreateBreakModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold mb-4">Create New Break</h2>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input
                        type="text"
                        v-model="newBreak.description"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        placeholder="E.g., Coffee break, Short walk, etc."
                    >
                    <div v-if="errors.description" class="mt-1 text-xs text-red-500">{{ errors.description }}</div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Points Cost</label>
                    <input
                        type="number"
                        v-model="newBreak.points_cost"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        min="1"
                    >
                    <div v-if="errors.points_cost" class="mt-1 text-xs text-red-500">{{ errors.points_cost }}</div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                    <input
                        type="number"
                        v-model="newBreak.duration_minutes"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        min="1"
                    >
                    <div v-if="errors.duration_minutes" class="mt-1 text-xs text-red-500">{{ errors.duration_minutes }}</div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <SecondaryButton @click="showCreateBreakModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="createBreak">Create Break</PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
