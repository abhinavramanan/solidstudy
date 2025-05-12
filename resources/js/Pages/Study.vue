<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import TimeTracker from '@/Components/TimeTracker.vue';
import { onMounted, ref, watch } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import { useTimeEntriesStore } from '@/utils/useTimeEntries';
import { storeToRefs } from 'pinia';
import type {
    CreateClientBody,
    CreateProjectBody,
    CreateTimeEntryBody,
    Project,
    TimeEntry,
    Client,
} from '@/packages/api/src';
import { useElementVisibility } from '@vueuse/core';
import { ClockIcon } from '@heroicons/vue/20/solid';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { PlusIcon } from '@heroicons/vue/16/solid';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { useTasksStore } from '@/utils/useTasks';
import { useProjectsStore } from '@/utils/useProjects';
import TimeEntryGroupedTable from '@/packages/ui/src/TimeEntry/TimeEntryGroupedTable.vue';
import { useTagsStore } from '@/utils/useTags';
import { useClientsStore } from '@/utils/useClients';
import TimeEntryCreateModal from '@/packages/ui/src/TimeEntry/TimeEntryCreateModal.vue';
import { getOrganizationCurrencyString } from '@/utils/money';
import TimeEntryMassActionRow from '@/packages/ui/src/TimeEntry/TimeEntryMassActionRow.vue';
import type { UpdateMultipleTimeEntriesChangeset } from '@/packages/api/src';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { canCreateProjects } from '@/utils/permissions';
import axios from 'axios';

const timeEntriesStore = useTimeEntriesStore();
const { timeEntries, allTimeEntriesLoaded } = storeToRefs(timeEntriesStore);
const { updateTimeEntry, fetchTimeEntries, createTimeEntry } =
    useTimeEntriesStore();

async function updateTimeEntries(
    ids: string[],
    changes: UpdateMultipleTimeEntriesChangeset
) {
    await useTimeEntriesStore().updateTimeEntries(ids, changes);
    fetchTimeEntries();
}

const loading = ref(false);
const loadMoreContainer = ref<HTMLDivElement | null>(null);
const isLoadMoreVisible = useElementVisibility(loadMoreContainer);
const currentTimeEntryStore = useCurrentTimeEntryStore();
const { currentTimeEntry } = storeToRefs(currentTimeEntryStore);
const { setActiveState } = currentTimeEntryStore;
const { tags } = storeToRefs(useTagsStore());

async function startTimeEntry(
    timeEntry: Omit<CreateTimeEntryBody, 'member_id'>
) {
    if (currentTimeEntry.value.id) {
        await setActiveState(false);
    }
    await createTimeEntry(timeEntry);
    fetchTimeEntries();
    useCurrentTimeEntryStore().fetchCurrentTimeEntry();
}

function deleteTimeEntries(timeEntries: TimeEntry[]) {
    useTimeEntriesStore().deleteTimeEntries(timeEntries);
    fetchTimeEntries();
}

watch(isLoadMoreVisible, async (isVisible) => {
    if (
        isVisible &&
        timeEntries.value.length > 0 &&
        !allTimeEntriesLoaded.value
    ) {
        loading.value = true;
        await timeEntriesStore.fetchMoreTimeEntries();
    }
});

onMounted(async () => {
    await timeEntriesStore.fetchTimeEntries();
    await fetchPointsInfo();
});

const showManualTimeEntryModal = ref(false);
const projectStore = useProjectsStore();
const { projects } = storeToRefs(projectStore);
const taskStore = useTasksStore();
const { tasks } = storeToRefs(taskStore);
const clientStore = useClientsStore();
const { clients } = storeToRefs(clientStore);

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}
async function createProject(
    project: CreateProjectBody
): Promise<Project | undefined> {
    return await useProjectsStore().createProject(project);
}
async function createClient(
    body: CreateClientBody
): Promise<Client | undefined> {
    return await useClientsStore().createClient(body);
}

const selectedTimeEntries = ref([] as TimeEntry[]);

async function clearSelectionAndState() {
    selectedTimeEntries.value = [];
    await fetchTimeEntries();
}

function deleteSelected() {
    deleteTimeEntries(selectedTimeEntries.value);
    selectedTimeEntries.value = [];
}

// Points system
const pointsInfo = ref({
    total_points: 0,
    spent_points: 0,
    available_points: 0,
});

async function fetchPointsInfo() {
    try {
        const response = await axios.get('/api/v1/organizations/' + currentTimeEntry.value.organization_id + '/points');
        pointsInfo.value = response.data;
    } catch (error) {
        console.error('Error fetching points info:', error);
    }
}
</script>

<template>
    <TimeEntryCreateModal
        v-model:show="showManualTimeEntryModal"
        :enable-estimated-time="isAllowedToPerformPremiumAction()"
        :create-project="createProject"
        :create-client="createClient"
        :create-tag="createTag"
        :create-time-entry="createTimeEntry"
        :projects
        :tasks
        :tags
        :clients></TimeEntryCreateModal>
    <AppLayout title="Study Tracking" data-testid="study_view">
        <MainContainer
            class="pt-5 lg:pt-8 pb-4 lg:pb-6">
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
            <div
                class="lg:flex items-end lg:divide-x divide-default-background-separator divide-y lg:divide-y-0 space-y-2 lg:space-y-0 lg:space-x-2">
                <div class="flex-1">
                    <TimeTracker></TimeTracker>
                </div>
                <div class="pb-2 pt-2 lg:pt-0 lg:pl-4 flex justify-center">
                    <SecondaryButton
                        class="w-full text-center flex justify-center"
                        :icon="PlusIcon"
                        @click="showManualTimeEntryModal = true"
                        >Manual study entry
                    </SecondaryButton>
                </div>
            </div>
        </MainContainer>
        <TimeEntryMassActionRow
            :selected-time-entries="selectedTimeEntries"
            :enable-estimated-time="isAllowedToPerformPremiumAction()"
            :can-create-project="canCreateProjects()"
            :all-selected="selectedTimeEntries.length === timeEntries.length"
            :delete-selected="deleteSelected"
            :projects="projects"
            :tasks="tasks"
            :tags="tags"
            :currency="getOrganizationCurrencyString()"
            :clients="clients"
            :update-time-entries="
                (args) =>
                    updateTimeEntries(
                        selectedTimeEntries.map((timeEntry) => timeEntry.id),
                        args
                    )
            "
            :create-project="createProject"
            :create-client="createClient"
            :create-tag="createTag"
            @submit="clearSelectionAndState"
            @select-all="selectedTimeEntries = [...timeEntries]"
            @unselect-all="selectedTimeEntries = []"></TimeEntryMassActionRow>
        <TimeEntryGroupedTable
            v-model:selected="selectedTimeEntries"
            :create-project
            :enable-estimated-time="isAllowedToPerformPremiumAction()"
            :can-create-project="canCreateProjects()"
            :clients
            :create-client
            :update-time-entry
            :update-time-entries
            :delete-time-entries
            :create-time-entry="startTimeEntry"
            :create-tag
            :projects="projects"
            :tasks="tasks"
            :currency="getOrganizationCurrencyString()"
            :time-entries="timeEntries"
            :tags="tags"></TimeEntryGroupedTable>
        <div v-if="timeEntries.length === 0" class="text-center pt-12">
            <ClockIcon class="w-8 text-icon-default inline pb-2"></ClockIcon>
            <h3 class="text-text-primary font-semibold">No study entries found</h3>
            <p class="pb-5">Create your first study entry now!</p>
        </div>
        <div ref="loadMoreContainer">
            <div
                v-if="loading && !allTimeEntriesLoaded"
                class="flex justify-center items-center py-5 text-text-primary font-medium">
                <LoadingSpinner></LoadingSpinner>
                <span> Loading more study entries... </span>
            </div>
            <div
                v-else-if="allTimeEntriesLoaded"
                class="flex justify-center items-center py-5 text-text-secondary font-medium">
                All study entries are loaded!
            </div>
        </div>
    </AppLayout>
</template>
