
import java.util.PriorityQueue;

public class IncidentManager {

    private PriorityQueue<Incident> waitingIncidents;
    private IncidentPriorityPolicy priorityPolicy;

    public IncidentManager() {
        priorityPolicy = new IncidentPriorityPolicy();

        waitingIncidents = new PriorityQueue<>(
                priorityPolicy.getPriorityComparator());
    }

    public void addIncident(Incident incident) {
        waitingIncidents.offer(incident);

        System.out.println(
                "Incident " + incident.getIncidentId()
                + " added to waiting queue.");
    }

    public Incident getNextIncident() {
        return waitingIncidents.poll();
    }

    public boolean hasPendingIncidents() {
        return !waitingIncidents.isEmpty();
    }

    public void displayWaitingIncidents() {
        if (waitingIncidents.isEmpty()) {
            System.out.println("No incidents waiting.");
            return;
        }

        PriorityQueue<Incident> temp =
                new PriorityQueue<>(waitingIncidents);

        System.out.println("\nWaiting incidents by priority:");

        while (!temp.isEmpty()) {
            Incident incident = temp.poll();

            System.out.println(
                    incident.getIncidentId()
                    + " | Intensity: "
                    + incident.getIntensity());
        }
    }
}