
import java.util.Comparator;

public class IncidentPriorityPolicy {

    public Comparator<Incident> getPriorityComparator() {
        return (a, b) -> {

            // Higher intensity gets higher priority
            if (a.getIntensity() != b.getIntensity()) {
                return Integer.compare(
                    b.getIntensity(), a.getIntensity());
            }

            // Critical infrastructure gets priority
            if (a.isCriticalInfrastructure()
                    != b.isCriticalInfrastructure()) {
                return Boolean.compare(
                    b.isCriticalInfrastructure(),
                    a.isCriticalInfrastructure());
            }

            // Higher population exposure gets priority
            if (a.getPopulationExposureRank()
                    != b.getPopulationExposureRank()) {
                return Integer.compare(
                    b.getPopulationExposureRank(),
                    a.getPopulationExposureRank());
            }

            // Earlier arrival gets priority when tied
            return Integer.compare(
                a.getArrivalOrder(), b.getArrivalOrder());
        };
    }
}