<!--
  Matomo - free/libre analytics platform
  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div v-if="report && report.type === 'teams'">
    <SelectMicrosoftTeamsWebhookUrl
        :is-required-fields-set="isRequiredFieldsSet"
        :model-value="report?.msTeamsWebhookUrl"
        @update:model-value="$emit('change', 'msTeamsWebhookUrl', $event)"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { Report } from 'ScheduledReports';
import SelectMicrosoftTeamsWebhookUrl from '../SelectMicrosoftTeamsWebhookUrl/SelectMicrosoftTeamsWebhookUrl.vue';

const REPORT_TYPE = 'teams';
export default defineComponent({
  props: {
    report: {
      type: Object,
      required: true,
    },
    isRequiredFieldsSet: {
      type: Boolean,
      default: false,
    },
    defaultFormat: {
      type: String,
      required: true,
    },
    defaultDisplayFormat: {
      type: Number,
      required: true,
    },
    defaultEvolutionGraph: {
      type: Boolean,
      required: true,
    },
  },
  components: {
    SelectMicrosoftTeamsWebhookUrl,
  },
  emits: ['change'],
  setup(props) {
    const {
      resetReportParametersFunctions,
      updateReportParametersFunctions,
      getReportParametersFunctions,
    } = window;
    if (!resetReportParametersFunctions[REPORT_TYPE]) {
      resetReportParametersFunctions[REPORT_TYPE] = (report: Report) => {
        report.displayFormat = props.defaultDisplayFormat;
        report.evolutionGraph = props.defaultEvolutionGraph;
        report.formatteams = props.defaultFormat;
        report.msTeamsWebhookUrl = '';
      };
    }
    if (!updateReportParametersFunctions[REPORT_TYPE]) {
      updateReportParametersFunctions[REPORT_TYPE] = (report: Report) => {
        if (!report?.parameters) {
          return;
        }
        ['displayFormat', 'evolutionGraph', 'msTeamsWebhookUrl'].forEach((field) => {
          if (field in report.parameters) {
            report[field] = report.parameters[field];
          }
        });
      };
    }
    if (!getReportParametersFunctions[REPORT_TYPE]) {
      getReportParametersFunctions[REPORT_TYPE] = (report: Report) => ({
        displayFormat: report.displayFormat,
        evolutionGraph: report.evolutionGraph,
        msTeamsWebhookUrl: report.msTeamsWebhookUrl,
      });
    }
  },
});
</script>
