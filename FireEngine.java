
import java.util.HashSet;
import java.util.Set;

public class FireEngine extends ResponseUnit {
    private Set<String> equipment;

    public FireEngine(String unitId, String currentLocation,
                      Set<String> equipment) {
        super(unitId, currentLocation);
        this.equipment = new HashSet<>(equipment);
    }

    public boolean supportsRequirement(String requiredEquipment) {
        return equipment.contains(requiredEquipment);
    }

    public void displayEquipment() {
        System.out.println("Fire Engine ID: " + getUnitId());
        System.out.println("Equipment: " + equipment);
    }
}