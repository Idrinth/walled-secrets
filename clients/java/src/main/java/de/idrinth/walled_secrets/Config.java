package de.idrinth.walled_secrets;

import java.util.prefs.Preferences;
import org.apache.commons.validator.GenericValidator;

public class Config
{
    private static final String KEY_SERVER_URL = "server";
    private static final String KEY_API_KEY = "apikey";
    private static final String KEY_FREQUENCY = "frequency";

    private volatile int frequency = 15;
    private volatile String server = "";
    private volatile String email = "";
    private volatile String apikey = "";
    private final Preferences prefs =  Preferences.userNodeForPackage(Main.class);

    public Config()
    {
        frequency = prefs.getInt(KEY_FREQUENCY, 15);
        server = prefs.get(KEY_SERVER_URL, "");
        apikey = prefs.get(KEY_API_KEY, "");
    }
    public int getFrequency() {
        return frequency;
    }
    public void setFrequency(int frequency) throws Exception {
        if (frequency < 10) {
            throw new InvalidConfiguration("Frequency is too high.");
        }
        if (frequency > 1000) {
            throw new InvalidConfiguration("Frequency is too low.");
        }
        prefs.putInt(KEY_FREQUENCY, frequency);
        this.frequency = frequency;
    }
    public String getServer() {
        return server;
    }
    public void setServer(String server) throws Exception {
        if (!GenericValidator.isUrl(server)) {
            throw new InvalidConfiguration("Server is invalid.");
        }
        prefs.put(KEY_SERVER_URL, server);
        this.server = server;
    }
    public String getEmail() {
        return email;
    }
    public void setEmail(String email) throws Exception {
        if (!GenericValidator.isEmail(email)) {
            throw new InvalidConfiguration("eMail is invalid.");
        }
        this.email = email;
    }
    public String getApikey() {
        return apikey;
    }
    public void setApikey(String apikey) throws Exception {
        if (!GenericValidator.matchRegexp(apikey, "^[a-zA-Z0-9]{255}$")) {
            throw new InvalidConfiguration("API-Key is invalid.");
        }
        prefs.put(KEY_API_KEY, apikey);
        this.apikey = apikey;
    }
}
